#!/usr/bin/env python3
"""
Find all Drupal modules and recipes with a hard dependency on drupal/ai
(https://www.drupal.org/project/ai) and write the results as JSON — or, with
--full-ecosystem, widen that to every module filed under drupal.org's
"Artificial Intelligence (AI)" project category too, dependency or not. This
script only collects and verifies data — render the output with render_md.py
(markdown) or render_html.py (sortable/filterable HTML).

Module candidate discovery (two sources, union-merged):
  1. drupal.org/project/ai/ecosystem  — curated AI ecosystem listing
  2. packages.drupal.org search "ai"  — package name/description search
  3. drupal.org AI project category   — modules tagged "Artificial
     Intelligence (AI)" via the api-d7 node listing.
     ONLY crawled with --full-ecosystem.

Module dependency verification (authoritative, reads composer.json directly):
  packages.drupal.org/files/packages/8/p2/drupal/{name}.json
  Confirms type=drupal-module AND drupal/ai is in the require field.
  Under --full-ecosystem, candidates from source 3 skip that second check —
  being filed under the AI project category is treated as its own
  qualification — and every module row records whether the dependency is
  present in a `requires_ai` boolean.
  Also supplies version, Drupal core constraint, and release datestamp.

Module additional data:
  updates.drupal.org           — release date fallback (when p2 has no datestamp)
  www.drupal.org/api-d7/node.json — usage/install count + security coverage

Recipes are verified separately, against the regular Packagist registry
(packages.drupal.org doesn't carry them at all) — see get_recipe_info() and
CLAUDE.md's "Data sources" section for details.

Requests are made sequentially from a single thread, with no client-side
throttling delay — per direct feedback from the drupal.org sysadmins,
concurrency (not request rate) is what risks looking like a DoS, and their
general rate limits are already enforced server-side via HTTP 429 with a
Retry-After header, which _http_get() honors.

Usage:
  python3 drupal_ai_dependents.py [--json FILE] [--history FILE] [--full-ecosystem]

  # Write JSON, then render separately (recommended):
  python3 drupal_ai_dependents.py --json results.json
  python3 render_md.py results.json -o results.md
  python3 render_html.py results.json -o results.html

  # Include AI-category-tagged modules that don't require drupal/ai:
  python3 drupal_ai_dependents.py --full-ecosystem --json results.json

Every normal run also appends this run's usage (modules) / downloads
(recipes) numbers to history.json (see --history), which the renderers read
to compute a Trend column comparing the two most recent runs.
"""

# Python's standard library modules — no composer/npm needed.
# Think of these like PHP's built-in extensions (json_decode, preg_match, etc.)
import argparse           # parses command-line flags like --json
import html               # html.unescape() — decodes entities like &#039; in text
import json               # like PHP's json_decode() / json_encode()
import re                 # like PHP's preg_match() / preg_replace()
import sys                # access to stdin/stdout/stderr and script exit
import time               # like PHP's sleep() and microtime()
import urllib.error       # HTTP error types thrown by urllib (like curl errors)
import urllib.parse       # URL building — like PHP's http_build_query()
import urllib.request     # makes HTTP requests — like PHP's curl or file_get_contents()
import xml.etree.ElementTree as ET  # XML parser — like PHP's SimpleXMLElement
from datetime import datetime, timezone  # date handling — like PHP's DateTime


# ---------------------------------------------------------------------------
# Configuration
# ---------------------------------------------------------------------------

# URL templates. The {name} placeholder is filled in later with str.format(),
# which works like sprintf() in PHP: P2_URL.format(name="ai_agents")
ECOSYSTEM_URL        = "https://www.drupal.org/project/ai/ecosystem"
PACKAGES_SEARCH_URL  = "https://packages.drupal.org/8/search.json"
P2_URL               = "https://packages.drupal.org/files/packages/8/p2/drupal/{name}.json"
RELEASE_HISTORY_URL  = "https://updates.drupal.org/release-history/{name}/current"
DRUPAL_API_URL       = "https://www.drupal.org/api-d7/node.json"
DRUPAL_PROJECT_URL   = "https://www.drupal.org/project/{name}"
INFO_YML_URL         = "https://git.drupalcode.org/project/{name}/-/raw/{version}/{name}.info.yml"

# Recipes (Drupal projects of type "drupal-recipe") are NOT published to
# packages.drupal.org at all — confirmed by a 404 on P2_URL for any recipe.
# They ARE published to the regular Packagist registry, in the same
# Composer v2 p2 format, so recipe verification uses a second registry.
RECIPE_SEARCH_URL    = "https://packagist.org/search.json"
RECIPE_P2_URL        = "https://repo.packagist.org/p2/drupal/{name}.json"
RECIPE_P2_DEV_URL    = "https://repo.packagist.org/p2/drupal/{name}~dev.json"
RECIPE_YML_URL       = "https://git.drupalcode.org/project/{name}/-/raw/{ref}/recipe.yml"
CURATED_RECIPES_URL  = "https://git.drupalcode.org/project/ai_dashboard/-/raw/1.0.x/ai_dashboard_recommended_recipes.yml?ref_type=heads"
PACKAGIST_STATS_URL  = "https://packagist.org/packages/drupal/{name}.json"

# A Python `set` is like a PHP array used as a lookup table (array_flip'd),
# where only unique values matter and order doesn't. Membership checks are O(1).
TARGET_VERSIONS = {10, 11}

# Taxonomy term ID of drupal.org's "Artificial Intelligence (AI)" project
# category (the "Project category" shown on project pages). Found via
# www.drupal.org/api-d7/taxonomy_term.json?name=Artificial intelligence (AI).
# Modules carrying this tag are included in the results even when they have
# no composer dependency on drupal/ai — see get_tagged_candidates().
AI_CATEGORY_TID = 204588

# No self-throttling delays and no concurrency. Per direct feedback from
# drupal.org sysadmins: concurrent requests are what risks tipping into DoS
# territory server-side, not request rate — so this script makes requests
# from a single thread, one at a time, back-to-back. The only backoff that
# matters is honoring HTTP 429's Retry-After header (see _http_get), which
# is how the upstream general rate limits communicate "slow down" — a fixed
# client-side delay guessed in advance is unnecessary on top of that.
MAX_RETRIES   = 4    # how many times to retry a failed/rate-limited API call before giving up
RETRY_BACKOFF = 2.0  # fallback wait in seconds when no Retry-After header is given; doubles after each retry

# Request headers sent with every HTTP call. The dict literal here is like
# PHP's associative array: ["User-Agent" => "..."]
HEADERS = {
    "User-Agent": "drupal-ai-dependents-script/1.0 (research tool)"
}


# ---------------------------------------------------------------------------
# HTTP helpers
# ---------------------------------------------------------------------------

# Function signatures in Python use type hints after the colon.
# `str` = string, `dict` = associative array, `None` = null,
# `dict | None` = a union type (like PHP 8's string|null).
# The return type after `->` shows what the function returns.
# `tuple[int, bytes, dict]` means it returns three values at once —
# like returning an array from a PHP function and list()ing it on the other end.
def _http_get(url: str, params: dict | None = None) -> tuple[int, bytes, dict]:
    """Make a GET request and return (status_code, body_bytes, headers_dict).

    The leading underscore in _http_get is a Python convention meaning
    "private/internal" — like protected in PHP. It signals this function
    is a helper not meant to be called from outside this file.

    Network-level errors (timeouts, SSL failures, connection resets) are
    retried up to MAX_RETRIES times with exponential backoff before raising.

    HTTP 429 (rate limited) is also retried, honoring the server's
    Retry-After header when present — this is the one form of backoff the
    drupal.org sysadmins asked us to keep. Every other 4xx/5xx status is
    returned as-is (not retried) so callers can handle e.g. 404 without a crash.
    """
    # If query parameters were provided, append them to the URL.
    # urllib.parse.urlencode({"s": "ai", "page": 2}) → "s=ai&page=2"
    # This is equivalent to PHP's http_build_query().
    if params:
        url = url + "?" + urllib.parse.urlencode(params)

    req = urllib.request.Request(url, headers=HEADERS)

    for attempt in range(MAX_RETRIES):
        try:
            with urllib.request.urlopen(req, timeout=30) as resp:
                return resp.status, resp.read(), dict(resp.headers)

        except urllib.error.HTTPError as exc:
            # HTTPError is raised for 4xx/5xx responses (unlike PHP's curl,
            # which just sets an error code). We catch it and return the status
            # code normally so callers can handle e.g. 404 without a crash.
            # `b""` is an empty byte string (the body was not retrieved).
            hdrs = dict(exc.headers) if exc.headers else {}

            if exc.code == 429 and attempt + 1 < MAX_RETRIES:
                # Respect the general rate limit: wait however long the
                # server tells us to via Retry-After, falling back to
                # exponential backoff only if that header is absent.
                raw = hdrs.get("Retry-After") or hdrs.get("retry-after")
                wait = float(raw) if raw else RETRY_BACKOFF * (2 ** attempt)
                print(
                    f"  [retry {attempt + 1}/{MAX_RETRIES}] 429 — waiting {wait:.0f}s"
                    f" ({url})",
                    file=sys.stderr,
                )
                time.sleep(wait)
                continue

            # Any other status (including a 429 with no retries left) is
            # not retried — a 404 won't become a 200 on retry.
            return exc.code, b"", hdrs
            
        except OSError as exc:
            # OSError covers network-level failures: timeouts, SSL errors,
            # DNS failures, connection resets. URLError is an OSError
            # subclass, but urllib doesn't wrap errors raised while reading
            # the response (e.g. TimeoutError from getresponse()), so the
            # raw socket errors must be caught here too. These are often
            # transient, so we retry with exponential backoff before giving
            # up. HTTPError is a subclass of URLError, but caught above first.

            if attempt + 1 < MAX_RETRIES:
                wait = RETRY_BACKOFF * (2 ** attempt)  # 2s, 4s, 8s …
                print(
                    f"  [retry {attempt + 1}/{MAX_RETRIES}] network error"
                    f" — waiting {wait:.0f}s ({exc})",
                    file=sys.stderr,
                )
                time.sleep(wait)
            else:
                raise RuntimeError(f"Request failed for {url}: {exc}") from exc


def _drupal_api_get(params: dict) -> dict | None:
    """Call the Drupal.org JSON API, retrying on 503 (rate-limit) responses.

    Returns the decoded JSON as a dict, or None if the request ultimately fails.
    `dict | None` as a return type is Python 3.10+ union syntax, equivalent
    to PHP 8's ?array or array|null.
    """
    backoff = RETRY_BACKOFF  # local copy so we can double it each iteration

    # range(MAX_RETRIES) produces [0, 1, 2, 3] — like PHP's for ($i = 0; $i < 4; $i++)
    for attempt in range(MAX_RETRIES):
        try:
            status, body, hdrs = _http_get(DRUPAL_API_URL, params)
        except RuntimeError:
            return None

        if status == 503:
            # 503 means the server is rate-limiting us. Check if it told us
            # how long to wait via the Retry-After header.
            # dict.get() is like PHP's $array['key'] ?? null — returns None
            # if the key doesn't exist, avoiding a KeyError (like PHP's
            # Undefined index notice).
            raw = hdrs.get("Retry-After") or hdrs.get("retry-after")

            # Use the server's suggested wait if given, otherwise use our
            # exponential backoff value. float() converts a string like "30"
            # to the number 30.0, like PHP's (float) cast.
            wait = float(raw) if raw else backoff

            print(f"  [retry {attempt+1}/{MAX_RETRIES}] 503 — waiting {wait:.0f}s",
                  file=sys.stderr)
            time.sleep(wait)
            backoff *= 2  # double the backoff for next time: 2 → 4 → 8 → 16
            continue      # jump back to the top of the for loop

        if status != 200:
            return None  # unrecoverable error (404, 500, etc.)

        # json.loads() is exactly PHP's json_decode($body, true) —
        # it parses a JSON string into a Python dict (associative array).
        return json.loads(body)

    # If we exhausted all retries, give up and return None.
    return None


# ---------------------------------------------------------------------------
# Candidate discovery — source 1: ecosystem page
# ---------------------------------------------------------------------------

# Compile the regex once at module load, rather than recompiling it on every
# call. This is like storing a compiled pattern in a static variable in PHP.
# The pattern captures the project machine name from links like:
#   href="/project/ai_image_alt_text"
_PROJECT_HREF_RE = re.compile(r'href="/project/([a-z0-9_]+)"')


def _scrape_ecosystem_page(page: int) -> tuple[list[str], bool]:
    """Scrape one page of the ecosystem listing.

    Returns a tuple of (list_of_machine_names, has_more_pages).
    `list[str]` is a typed list — like PHP's array where every value is a string.
    `bool` is True/False.
    """
    # Only add the `page` query parameter for pages after the first.
    # The conditional expression `x if condition else y` is Python's
    # ternary operator, equivalent to PHP's `condition ? x : y`.
    status, body, _ = _http_get(ECOSYSTEM_URL, {"page": page} if page > 0 else None)

    if status != 200:
        # Return an empty list and False to signal "nothing found, stop paging".
        # `[]` is an empty list, like PHP's [].
        return [], False

    # Decode the raw bytes to a UTF-8 string, replacing any invalid characters.
    # This is like PHP's mb_convert_encoding() or utf8_decode().
    text = body.decode("utf-8", errors="replace")

    # A `set` here acts as a "seen" tracker to deduplicate results in O(1).
    # Think of it as array_flip(array_unique($names)) in PHP.
    seen, names = set(), []

    # finditer() works like preg_match_all() in PHP — it returns all matches.
    # Each match `m` is an object; m.group(1) is the first capture group,
    # equivalent to PHP's $matches[1][0].
    for m in _PROJECT_HREF_RE.finditer(text):
        n = m.group(1)
        # `not in` is Python's equivalent of !in_array() or !isset($seen[$n]).
        if n not in seen and n != "ai":
            seen.add(n)     # add to set (like $seen[$n] = true in PHP)
            names.append(n) # append to list (like $names[] = $n in PHP)

    # Check if the page HTML contains a link to the next page number.
    # re.search() is like preg_match() — returns a match object or None.
    # bool() converts it to True/False, like (bool) in PHP.
    has_more = bool(re.search(r'[?&]page=' + str(page + 1), text))

    return names, has_more


def get_ecosystem_candidates() -> list[str]:
    """Paginate through all ecosystem pages and return unique machine names."""
    all_names, seen = [], set()
    page = 0

    # Python's `while True` with a `break` is a common pattern for loops
    # where the exit condition is checked mid-loop, like PHP's do/while.
    while True:
        print(f"    Page {page} …", file=sys.stderr)

        # Unpack the tuple returned by _scrape_ecosystem_page into two variables.
        # Equivalent to PHP's [$names, $has_more] = _scrape_ecosystem_page($page);
        names, has_more = _scrape_ecosystem_page(page)

        for n in names:
            if n not in seen:
                seen.add(n)
                all_names.append(n)

        if not has_more:
            break  # exit the while loop

        page += 1  # equivalent to $page++ in PHP

    return all_names


# ---------------------------------------------------------------------------
# Candidate discovery — source 2: packages.drupal.org search
# ---------------------------------------------------------------------------

def get_search_candidates(query: str = "ai") -> list[str]:
    """Paginate packages.drupal.org search and return machine names.

    `query: str = "ai"` defines a default parameter value, just like
    PHP's function get_search_candidates(string $query = 'ai').
    """
    all_names, seen = [], set()

    # `str | None` means this variable can hold a string or None (null).
    # We start with the base search URL and will replace it with the
    # server-provided `next` URL on subsequent pages.
    url: str | None = PACKAGES_SEARCH_URL
    params: dict | None = {"s": query, "per_page": 100}

    while url:
        # Pass params on the first request; subsequent requests use the
        # complete `next` URL which already has parameters embedded.
        try:
            status, body, _ = _http_get(url, params)
        except RuntimeError as exc:
            print(f"  Warning: packages.drupal.org search failed: {exc}",
                  file=sys.stderr)
            print("  Continuing with ecosystem candidates only.", file=sys.stderr)
            break
        params = None  # clear params — the next URL already includes them

        if status != 200 or not body:
            break

        # json.loads() decodes the JSON response body into a Python dict.
        data = json.loads(body)

        # data.get("results", []) safely retrieves the "results" key,
        # defaulting to an empty list if missing — like PHP's
        # $data['results'] ?? []
        for pkg in data.get("results", []):
            name = pkg.get("name", "")

            # We only want packages in the drupal/* namespace.
            # str.startswith() is like PHP's str_starts_with().
            if name.startswith("drupal/"):
                # str.removeprefix() strips a known prefix — like PHP's
                # substr($name, strlen('drupal/')) or ltrim($name, 'drupal/')
                # but safer (only removes it if actually present).
                machine = name.removeprefix("drupal/")
                if machine not in seen and machine != "ai":
                    seen.add(machine)
                    all_names.append(machine)

        # The API returns the next page URL in a "next" key, or omits it on
        # the last page. `or None` converts a falsy empty string to None,
        # which will stop the while loop. Like PHP's $url = $data['next'] ?: null;
        url = data.get("next") or None

    return all_names


# ---------------------------------------------------------------------------
# Candidate discovery — source 3: drupal.org "Artificial Intelligence (AI)"
# project category
# ---------------------------------------------------------------------------

def get_tagged_candidates() -> list[str]:
    """Paginate the drupal.org api-d7 node listing for every module whose
    "Project category" includes "Artificial Intelligence (AI)".

    This is the tag that project maintainers pick on their drupal.org project
    page, so it captures the maintainer's own claim that the module is
    AI-related — including modules with no composer dependency on drupal/ai
    at all (e.g. WebMCP integrations), which the other two sources would
    verify-and-reject. Names from this source are therefore exempted from
    the drupal/ai `require` check in get_p2_info(); they still must exist on
    packages.drupal.org as a Drupal 10/11-compatible drupal-module.

    Uses _drupal_api_get(), which already handles the drupal.org API's
    503-style rate limiting with Retry-After support.
    """
    all_names, seen = [], set()
    page = 0

    while True:
        data = _drupal_api_get({
            "type": "project_module",
            "taxonomy_vocabulary_3": AI_CATEGORY_TID,
            "limit": 50,
            "page": page,
        })
        if data is None:
            # A failed page means the tail of the listing is unknown — warn
            # loudly rather than silently pretending the listing ended here.
            print(f"  Warning: AI category listing page {page} failed; "
                  f"continuing with {len(all_names)} tagged names collected so far.",
                  file=sys.stderr)
            break

        for node in data.get("list", []):
            n = node.get("field_project_machine_name")
            if n and n not in seen and n != "ai":
                seen.add(n)
                all_names.append(n)

        # Same next-page convention as packages.drupal.org search: the API
        # includes a "next" link until the last page.
        if not data.get("next"):
            break
        page += 1

    return all_names


# ---------------------------------------------------------------------------
# Candidate discovery — recipes, source 1: Packagist type search
# ---------------------------------------------------------------------------

def get_recipe_search_candidates() -> list[str]:
    """Paginate the regular Packagist registry for drupal-recipe packages.

    Recipes aren't published to packages.drupal.org at all, so this queries
    the main Packagist instead — `type=drupal-recipe` alone returns ~670
    packages regardless of AI-relatedness, which would roughly double this
    script's run time to verify. Adding `q="ai"` narrows that to ~60 while
    still catching every recipe we need (confirmed live, including
    drupal/drupal_cms_ai). This accepts the same "could miss a non-'ai'-named
    dependent" tradeoff already made by get_search_candidates() for modules —
    false positives are expected and filtered out at verification.
    """
    all_names, seen = [], set()

    url: str | None = RECIPE_SEARCH_URL
    params: dict | None = {"q": "ai", "type": "drupal-recipe", "per_page": 100}

    while url:
        try:
            status, body, _ = _http_get(url, params)
        except RuntimeError as exc:
            print(f"  Warning: Packagist recipe search failed: {exc}",
                  file=sys.stderr)
            print("  Continuing without Packagist recipe candidates.", file=sys.stderr)
            break
        params = None  # clear params — the next URL already includes them

        if status != 200 or not body:
            break

        data = json.loads(body)

        for pkg in data.get("results", []):
            name = pkg.get("name", "")
            if name.startswith("drupal/"):
                machine = name.removeprefix("drupal/")
                if machine not in seen:
                    seen.add(machine)
                    all_names.append(machine)

        url = data.get("next") or None

    return all_names


# ---------------------------------------------------------------------------
# Candidate discovery — recipes, source 2: curated AI recipe list
# ---------------------------------------------------------------------------

# Matches a `machineName:` value anywhere in the curated YAML file, e.g.
# `  machineName: ai_recipe_image_classification`. We don't parse the YAML
# structure at all (no PyYAML in this project, same as _INFO_NAME_RE's
# approach for info.yml) — every name extracted here still goes through full
# p2 verification against Packagist, so a loose regex is safe.
_CURATED_MACHINE_NAME_RE = re.compile(r'machineName:\s*(\S+)')


def get_curated_recipe_candidates() -> list[str]:
    """Fetch the AI Dashboard module's hand-maintained list of AI recipes.

    This is a supplementary source, not authoritative — it's maintained by
    a third party and isn't guaranteed exhaustive or even currently accurate.
    It's useful because it catches at least one real AI recipe
    (drupal/drupal_cms_ai) that doesn't appear on the ecosystem page at all.
    Every name returned here still gets verified via get_recipe_info() like
    any other candidate.
    """
    try:
        status, body, _ = _http_get(CURATED_RECIPES_URL)
    except RuntimeError as exc:
        print(f"  Warning: curated recipe list fetch failed: {exc}", file=sys.stderr)
        return []

    if status != 200 or not body:
        print(f"  Warning: curated recipe list returned HTTP {status}", file=sys.stderr)
        return []

    text = body.decode("utf-8", errors="replace")

    seen, names = set(), []
    for m in _CURATED_MACHINE_NAME_RE.finditer(text):
        n = m.group(1)
        if n not in seen:
            seen.add(n)
            names.append(n)

    return names


# ---------------------------------------------------------------------------
# Dependency verification — packages.drupal.org p2
# ---------------------------------------------------------------------------

def _core_is_d10_d11(constraint: str) -> bool:
    """Check whether a drupal/core Composer version constraint covers D10 or D11.

    For example: "^10.3 || ^11" → True, "^8 || ^9" → False.
    A missing or wildcard ("*") constraint is treated as compatible because
    the packages.drupal.org/8 repository only serves modern Drupal packages.
    """
    if not constraint or constraint == "*":
        return True

    # `any()` is like PHP's array_filter() used as a boolean check —
    # it returns True as soon as one element is truthy, short-circuiting
    # the rest. Here we check if any TARGET_VERSION number (10, 11) appears
    # as a whole word (\b is a word boundary) in the constraint string.
    # rf"\b{v}\b" is a raw f-string — the r prefix stops Python from
    # interpreting backslashes, and f allows {v} substitution.
    # Result: rf"\b{10}\b" → the regex pattern r"\b10\b"
    return bool(any(re.search(rf"\b{v}\b", constraint) for v in TARGET_VERSIONS))


def _select_best_ai_dependent_version(
    versions: list[dict], required_type: str, require_ai: bool = True
) -> dict | None:
    """Filter a p2 version list down to the newest version that matches
    `required_type` and has a Drupal 10/11-compatible drupal/ai dependency.

    With require_ai=False the drupal/ai `require` check is skipped — used for
    modules discovered via the drupal.org AI project category, which qualify
    by tag rather than by composer dependency (type and core-constraint
    checks still apply).

    Shared by get_p2_info() (modules, packages.drupal.org) and
    get_recipe_info() (recipes, Packagist) — the verification rules are
    identical, only the registry URL and the required `type` differ.

    `versions` is the raw p2 version list, newest-first, each entry the full
    composer.json contents for that release. The `type` field is stable
    across versions, so checking only the first (latest) entry is sufficient.

    Returns the chosen version dict (still containing the full raw
    composer.json fields for that release), or None if nothing qualifies.
    """
    if not versions or versions[0].get("type") != required_type:
        return None

    # Collect all versions that satisfy requirements, then pick the best one.
    candidates = []
    for v in versions:
        require = v.get("require") or {}
        if require_ai and "drupal/ai" not in require:
            continue
        core_constraint = require.get("drupal/core", "")
        if not _core_is_d10_d11(core_constraint):
            continue
        candidates.append(v)

    if not candidates:
        return None

    # Prefer the newest stable release; fall back to the newest pre-release
    # if no stable version exists (e.g. module is still in beta).
    return next(
        (v for v in candidates if detect_stability(v.get("version", "")) == "stable"),
        candidates[0],
    )


def get_p2_info(machine_name: str, require_ai: bool = True) -> dict | None:
    """Fetch and parse a module's Composer p2 metadata from packages.drupal.org.

    The p2 format is Composer's v2 repository format. Each package gets its own
    JSON file listing every published version along with its full composer.json
    contents — so we can read `require`, `type`, and `extra` directly.

    require_ai=False relaxes the drupal/ai dependency check — used for modules
    that qualify via the drupal.org AI project category instead. The returned
    `ai_constraint` is None when the chosen version has no drupal/ai require.

    Returns a dict with version/date/constraints, or None if the module fails
    verification (exists, is a module, D10/11-compatible, and — unless
    require_ai=False — requires drupal/ai).
    """
    url = P2_URL.format(name=machine_name)
    try:
        status, body, _ = _http_get(url)
    except RuntimeError:
        return None

    # A 404 means this package simply doesn't exist on packages.drupal.org.
    if status != 200 or not body:
        return None

    try:
        data = json.loads(body)
    except json.JSONDecodeError:
        # Malformed JSON — skip this package.
        return None

    # The p2 JSON structure is: {"packages": {"drupal/ai_agents": [{...}, {...}]}}
    # Versions are listed newest-first. This is equivalent to PHP's:
    # $versions = $data['packages']["drupal/$machine_name"] ?? [];
    versions = data.get("packages", {}).get(f"drupal/{machine_name}", [])

    # Type check excludes recipes (`drupal-recipe`), profiles, distributions —
    # those are handled by the separate get_recipe_info() pipeline.
    best = _select_best_ai_dependent_version(versions, "drupal-module", require_ai)
    if best is None:
        return None

    require = best.get("require") or {}
    core_constraint = require.get("drupal/core", "")
    datestamp = (best.get("extra") or {}).get("drupal", {}).get("datestamp")
    date = (
        datetime.fromtimestamp(int(datestamp), tz=timezone.utc).strftime("%Y-%m-%d")
        if datestamp
        else None
    )
    return {
        "version":         best.get("version", ""),
        "date":            date,
        "ai_constraint":   require.get("drupal/ai"),
        "core_constraint": core_constraint,
        # The package's own composer.json "name" field, e.g. "drupal/ai_agents" —
        # already present in the p2 payload, so no extra request is needed.
        "composer_name":   best.get("name", ""),
    }


# ---------------------------------------------------------------------------
# Dependency verification — recipes: repo.packagist.org p2
# ---------------------------------------------------------------------------

def get_recipe_info(machine_name: str) -> dict | None:
    """Fetch and parse a recipe's Composer p2 metadata from Packagist.

    Recipes aren't on packages.drupal.org at all (confirmed: 404 on P2_URL
    for any recipe), so this hits the regular Packagist registry instead —
    same Composer v2 p2 format, just a different host and required `type`.

    Unlike packages.drupal.org's p2 files, Packagist's have a real ISO-8601
    `time` field on every version, so there's no datestamp workaround and
    no need for an updates.drupal.org date fallback at all.

    Composer splits stable/tagged releases (this URL) from branch/dev
    snapshots into a separate "~dev" p2 file — confirmed live: a recipe
    with only a "1.x-dev" release returns an EMPTY version list here, with
    its actual data only in RECIPE_P2_DEV_URL. Both are fetched and merged
    so dev-only recipes (no tagged release yet) still verify correctly.

    Returns a dict with version/date/constraints, or None if the recipe
    fails verification (doesn't exist, isn't a recipe, or doesn't have a
    Drupal 10/11-compatible drupal/ai dependency).
    """
    versions = []
    for url in (RECIPE_P2_URL.format(name=machine_name),
                RECIPE_P2_DEV_URL.format(name=machine_name)):
        try:
            status, body, _ = _http_get(url)
        except RuntimeError:
            continue

        if status != 200 or not body:
            continue

        try:
            data = json.loads(body)
        except json.JSONDecodeError:
            continue

        versions += data.get("packages", {}).get(f"drupal/{machine_name}", [])

    best = _select_best_ai_dependent_version(versions, "drupal-recipe")
    if best is None:
        return None

    require = best.get("require") or {}
    core_constraint = require.get("drupal/core", "")
    raw_time = best.get("time")
    date = (
        datetime.fromisoformat(raw_time).strftime("%Y-%m-%d")
        if raw_time
        else None
    )
    return {
        "version":         best.get("version", ""),
        "date":            date,
        "ai_constraint":   require["drupal/ai"],
        "core_constraint": core_constraint,
        "composer_name":   best.get("name", ""),
    }


# ---------------------------------------------------------------------------
# Module label + description — git.drupalcode.org (*.info.yml `name`/`description`)
# ---------------------------------------------------------------------------

# Matches top-level `name:` / `description:` keys in a .info.yml (or recipe.yml)
# file, e.g. `name: AI Core` or `description: Build AI agents.`. Anchored with
# MULTILINE so `^`/`$` match line boundaries, not the whole file. Same loose,
# no-YAML-library approach used throughout this script — only single-line
# scalar values are handled (block scalars `>` / `|` are treated as absent,
# see _strip_yaml_quotes / _parse_info_yml below).
_INFO_NAME_RE        = re.compile(r'^name:\s*(.+?)\s*$', re.MULTILINE)
_INFO_DESCRIPTION_RE = re.compile(r'^description:\s*(.+?)\s*$', re.MULTILINE)

# A bare YAML block-scalar indicator (`>`, `|`, `>-`, `|+2`, …) as the whole
# value means the real text is on the following indented lines, which the
# single-line regex above can't capture — treat that as "no description".
_YAML_BLOCK_INDICATOR_RE = re.compile(r'^[|>][+-]?\d*$')

# Descriptions come straight from info.yml/recipe.yml and can run long, so
# set a maximum length.
DESCRIPTION_MAX_LEN = 200


def _truncate_description(text: str, limit: int = DESCRIPTION_MAX_LEN) -> str:
    """Collapse whitespace and truncate long text descriptions with a ..."""
    text = " ".join(text.split())
    if len(text) <= limit:
        return text
    return text[:limit - 3].rstrip() + "..."


def _strip_yaml_quotes(value: str) -> str:
    """Strip a single layer of matching quotes from a YAML scalar."""
    if len(value) >= 2 and value[0] == value[-1] and value[0] in "'\"":
        return value[1:-1]
    return value


def _parse_info_yml(text: str) -> tuple[str | None, str | None]:
    """Extract the `name:` (label) and `description:` scalars from an
    info.yml / recipe.yml file's raw text.

    Both are optional top-level keys. Returns (label_or_None,
    description_or_None) with one layer of YAML quotes stripped from each and
    block-scalar placeholders discarded. The description is truncated to
    DESCRIPTION_MAX_LEN here so renderers never have to.
    """
    name_match = _INFO_NAME_RE.search(text)
    desc_match = _INFO_DESCRIPTION_RE.search(text)

    label = _strip_yaml_quotes(name_match.group(1).strip()) if name_match else None

    description = None
    if desc_match:
        description = _strip_yaml_quotes(desc_match.group(1).strip())
        if _YAML_BLOCK_INDICATOR_RE.match(description):
            description = None
        else:
            description = _truncate_description(description)

    return (label or None, description or None)


def get_module_label_and_description(
    machine_name: str, version: str
) -> tuple[str | None, str | None]:
    """Fetch a module's label and description from its {name}.info.yml file.

    Reads the raw file straight from git.drupalcode.org at the tag matching
    the module's release version. The `name:` key lives only here, it isn't
    part of composer.json or any JSON API response,  and the same file's
    `description:` key carries the module's own full description.

    Returns (label_or_None, description_or_None).
    """
    url = INFO_YML_URL.format(name=machine_name, version=version)
    try:
        status, body, _ = _http_get(url)
    except RuntimeError:
        return None, None
    if status != 200 or not body:
        return None, None

    return _parse_info_yml(body.decode("utf-8", errors="replace"))


# ---------------------------------------------------------------------------
# Recipe label and description — git.drupalcode.org (recipe.yml `name`/`description`)
# ---------------------------------------------------------------------------

_DEV_SUFFIX_RE = re.compile(r'-dev$')


def _git_ref_for_version(version: str) -> str:
    """Convert a Composer dev-version string to the git ref GitLab actually
    serves raw files from.

    Composer's "-dev" suffix (e.g. "1.x-dev") marks a branch alias, not a
    real git tag — GitLab has no "1.x-dev" ref, only "1.x". Confirmed live:
    .../−/raw/1.x/composer.json → 200, .../−/raw/1.x-dev/composer.json → 404.
    Stable versions (e.g. "1.1.0") pass through unchanged since they ARE
    real tags.

    '1.x-dev' → '1.x'
    '1.1.0'   → '1.1.0'
    """
    return _DEV_SUFFIX_RE.sub('', version)


def get_recipe_label_and_description(
    machine_name: str, version: str
) -> tuple[str | None, str | None]:
    """Fetch a recipe's label and description from its recipe.yml file.

    Recipes don't have a {name}.info.yml like modules do — their display
    title and description live in the `name:` / `description:` keys of a
    fixed-filename recipe.yml at the repo root instead. Reuses the shared
    _parse_info_yml() since those keys aren't module-specific. Both come from
    this single fetch; no extra request is needed for the description.

    Returns (label_or_None, description_or_None). The description is None when
    the recipe.yml has no `description:` key (no other source is consulted).
    """
    ref = _git_ref_for_version(version)
    url = RECIPE_YML_URL.format(name=machine_name, ref=ref)
    try:
        status, body, _ = _http_get(url)
    except RuntimeError:
        return None, None
    if status != 200 or not body:
        return None, None

    return _parse_info_yml(body.decode("utf-8", errors="replace"))


# ---------------------------------------------------------------------------
# Recipe stats (downloads + stars) — packagist.org statistics API
# ---------------------------------------------------------------------------

def get_recipe_stats(machine_name: str) -> tuple[int | None, int | None]:
    """Fetch Packagist stats for a recipe in one request.

    Returns (downloads, stars) where:
    - downloads: package.downloads.total (total Composer install count)
    - stars:     package.favers          (Packagist "star" count)
    Both values are None on any error (non-200, missing key, decode failure).
    """
    url = PACKAGIST_STATS_URL.format(name=machine_name)
    try:
        status, body, _ = _http_get(url)
    except RuntimeError:
        return None, None
    if status != 200 or not body:
        return None, None
    try:
        data = json.loads(body)
    except json.JSONDecodeError:
        return None, None
    pkg = data.get("package") or {}
    try:
        downloads = int(pkg["downloads"]["total"])
    except (KeyError, TypeError, ValueError):
        downloads = None
    try:
        stars = int(pkg["favers"])
    except (KeyError, TypeError, ValueError):
        stars = None
    return downloads, stars


# ---------------------------------------------------------------------------
# Release date fallback — updates.drupal.org
# ---------------------------------------------------------------------------

def get_release_date_fallback(machine_name: str, version: str) -> str | None:
    """Look up a release date from updates.drupal.org.

    Only called when the p2 file has no datestamp. The updates.drupal.org
    endpoint returns an XML document listing all releases — the same feed
    Drupal core uses to display "update available" messages in the admin UI.
    """
    url = RELEASE_HISTORY_URL.format(name=machine_name)
    status, body, _ = _http_get(url)
    if status != 200 or not body:
        return None

    try:
        # ET.fromstring() parses an XML string into a tree of Element objects.
        # This is like PHP's new SimpleXMLElement($body) or
        # $doc = new DOMDocument(); $doc->loadXML($body);
        root = ET.fromstring(body)
    except ET.ParseError:
        return None

    first_published_date = None

    # root.findall(".//release") finds all <release> elements anywhere in the
    # XML tree. The ".//..." XPath syntax is like PHP's
    # $xpath->query('//release'). Each element is like a SimpleXMLElement node.
    for release in root.findall(".//release"):

        # release.findtext("status") gets the text content of the <status>
        # child element, like PHP's (string)$release->status
        if release.findtext("status") != "published":
            continue

        ts = release.findtext("date")  # Unix timestamp as a string
        if not ts:
            continue

        # Record the first published release date we encounter as a fallback
        # in case we never find the exact version match below.
        if first_published_date is None:
            first_published_date = datetime.fromtimestamp(
                int(ts), tz=timezone.utc
            ).strftime("%Y-%m-%d")

        # If this release matches the exact version from the p2 file, return
        # its date immediately (like PHP's `return` inside a foreach).
        if release.findtext("version") == version:
            return datetime.fromtimestamp(int(ts), tz=timezone.utc).strftime("%Y-%m-%d")

    # Return the most recent published date even if we didn't find an exact
    # version match — better than returning nothing.
    return first_published_date


# ---------------------------------------------------------------------------
# Usage count — Drupal.org API
# ---------------------------------------------------------------------------

def get_usage_and_security(machine_name: str) -> tuple[int | None, bool]:
    """Fetch the active install count and security advisory coverage.

    Both come from the same Drupal.org JSON API node, so they're fetched
    together to avoid a second API round-trip.

    The API returns a `project_usage` object like:
      {"1.0.x": 1500, "1.1.x": 3200}
    We sum all version counts to get a single total.

    `field_security_advisory_coverage` is `"covered"` for projects covered
    by Drupal's security advisory policy, `"not-covered"` otherwise. Any
    other/missing value is treated as not covered.

    Returns (usage_or_None, is_security_covered).
    """
    data = _drupal_api_get({
        "field_project_machine_name": machine_name,
        "limit": 1,  # we only need the one matching project node
    })
    if not data:
        return None, False

    # data.get("list", []) retrieves the array of node results.
    nodes = data.get("list", [])
    if not nodes:
        return None, False

    # nodes[0] is the first (and only) result — like PHP's $nodes[0].
    node = nodes[0]
    usage = node.get("project_usage") or {}

    # This is a generator expression inside sum() — a concise way to
    # convert all values to int and add them up. It's equivalent to:
    #   $total = 0;
    #   foreach ($usage as $v) { $total += (int)$v; }
    # The API returns counts as strings, so we cast each with int().
    total_usage = sum(int(v) for v in usage.values()) if usage else None

    is_covered = node.get("field_security_advisory_coverage") == "covered"

    return total_usage, is_covered


# ---------------------------------------------------------------------------
# Helpers
# ---------------------------------------------------------------------------

def human_name(machine_name: str) -> str:
    """Convert a machine name to a readable title.

    'ai_image_alt_text' → 'Ai Image Alt Text'

    str.replace() is like PHP's str_replace().
    str.title() capitalises the first letter of each word,
    like PHP's ucwords().
    """
    return machine_name.replace("_", " ").title()


_STABILITY_RE = re.compile(r'-(?P<level>alpha|beta|rc|dev)', re.IGNORECASE)

def detect_stability(version: str) -> str:
    """Return the stability level of a version string.

    '1.2.3'        → 'stable'
    '1.0.0-alpha1' → 'alpha'
    '2.0.0-beta'   → 'beta'
    '1.5.0-rc1'    → 'rc'
    '1.0.0-dev'    → 'dev'
    ''             → 'stable'
    """
    if not version:
        return "stable"
    m = _STABILITY_RE.search(version)
    return m.group("level").lower() if m else "stable"


# ---------------------------------------------------------------------------
# Categorization
# ---------------------------------------------------------------------------
#
# A deterministic, rule-based classifier that tags each package with one or
# more categories, derived from its label, description and machine name. No
# network calls and no AI — same spirit as detect_stability() above, just with
# keyword tables instead of one regex.
#
# It runs automatically as part of a normal collection run (apply_categories()
# is called before the JSON is written), and can also be re-applied to an
# existing results.json without re-crawling via `--categorize FILE` — handy for
# iterating on the keyword rules below (the ~20-25 minute crawl is the slow
# part; classification is instant).

# The canonical category names, in display order. Single source of truth for
# the taxonomy: detect_categories() returns matches in this order, and the
# renderers order their category filter by it.
CATEGORIES = [
    "Tool",
    "Cloud Providers",
    "Local Providers",
    "Editorial",
    "Content",
    "Search",
    "Chat",
    "Automation",
    "Agents",
    "Analytics",
    "Media",
    "Vector Database",
    "SEO & Metadata",
    "Translation",
    "Safety & Governance",
    "Accessibility",
    "Developer Tools",
    "Evaluation & Testing",
]

# Value used when no rule matches. Deliberately NOT one of CATEGORIES so it
# stands out in the output and filter as "needs a rule or an override".
UNCATEGORIZED = "Uncategorized"

# Named LLM/inference vendors that don't follow the ai_provider_* naming
# convention, so they must be recognised by brand name. Kept to distinctive
# brand strings (not common English words) to avoid false positives.
_CLOUD_VENDORS = [
    "openai", "anthropic", "azure", "gemini", "vertex", "bedrock", "groq",
    "mistral", "deepseek", "deepl", "huggingface", "openrouter", "cloudflare",
    "workers ai", "elevenlabs", "deepgram", "fireworks", "dreamstudio",
    "stable diffusion", "amazee", "yandex", "baidu", "moonshot", "zhipu",
    "assemblyai", "langdock", "apertus", "litellm", "mittwald", "bytedance",
    "siliconflow", "infomaniak", "quant cloud", "nanobanana", "dropsolid",
    "dxpr", "writer ai", "acquia",
]

# Local model runtimes — used both as Local Providers signals and as
# Cloud Providers excludes (a provider is local OR cloud, not both).
_LOCAL_RUNTIMES = [
    "ollama", "lmstudio", "lm studio", "vllm", "llama.cpp", "llama_cpp",
    "anythingllm", "browser provider", "trigger from the browser",
]

# Keyword tables, all matched as plain lowercased substrings (like a series of
# str_contains() checks in PHP — no regex).
#   _KEYWORDS_NAME — matched only against the NAME (machine name + label). Used
#                    by "noisy" categories (Agents, Providers) where a mention
#                    in the description alone would over-match.
#   _KEYWORDS_ANY  — matched against the FULL text (machine name + label +
#                    description). Most categories use this.
# A category matches when no _EXCLUDES term is present AND (a NAME term hits the
# name OR an ANY term hits the full text).
_KEYWORDS_NAME = {
    "Tool": ["ai_tool_", "_tool", "tools"],
    "Cloud Providers": ["ai_provider_", "_provider"],
    "Agents": ["agent"],
    "Vector Database": ["ai_vdb_provider_", "vdb"],
    "SEO & Metadata": ["metatag", "schema_metatag", "ai_seo", "jsonld"],
    "Translation": ["ai_translate", "tmgmt"],
    "Editorial": ["ckeditor"],
    "Evaluation & Testing": ["ai_eval", "_eval", "autoeval"],
    "Developer Tools": ["twig_to_sdc", "api_normalization"],
    "Accessibility": ["editoria11y", "alt_text"],
    "Automation": ["eca", "automator"],
}

_KEYWORDS_ANY = {
    "Tool": ["mcp", "as a tool", "chromium", "screenshot"],
    "Cloud Providers": _CLOUD_VENDORS + ["enables the use of"],
    "Local Providers": _LOCAL_RUNTIMES,
    "Editorial": [
        "ckeditor", "editor", "paraphras", "proofread", "gutenberg",
        "edit mode", "wysiwyg", "tone",
    ],
    "Content": [
        "content", "summar", "taxonomy tag", "single page importer",
        "entity intake", "article field", "populate", "content generation",
    ],
    "Search": [
        "search", "rag", "retrieval-augmented", "retrieval augmented",
        "semantic", "embedding", "related content", "reciprocal rank", "rrf",
        "searxng", "solr", "pageindex",
    ],
    "Chat": [
        "chat", "chatbot", "chat bot", "conversational", "mention bot",
    ],
    "Automation": [
        "automat", "workflow", "feeds", "tamper", "migrat", "batch",
        "pipeline", "lifecycle", "404 redirect", "queue",
    ],
    "Agents": ["agentic", "ossa", "aiagent"],
    "Analytics": [
        "analy", "audit", "sentiment", "brand voice", "monitor", "observ",
        "dashboard", "inspector", "metering", "token", "cost", "budget",
        "quota", "usage limit", "report", "insight", "watchdog",
    ],
    "Media": [
        "image", "audio", "video", "speech", "text-to-speech",
        "text to speech", "speech-to-text", "vision", "ocr", "text-to-image",
        "transcription", "pdf", "pexels", "pixabay", "nanobanana",
        "talking-head", "mp3", "diffusion", "media",
    ],
    "Vector Database": [
        "vector database", "vector db", "vector", "pgvector", "dense vector",
        "dense_vector", "pinecone", "milvus", "qdrant", "zilliz", "weaviate",
        "sqlite-vec", "hnsw",
    ],
    "SEO & Metadata": [
        "seo", "geo", "meta tag", "meta description", "metatag", "schema.org",
        "json-ld", "structured data", "llms.txt", "sitemap",
    ],
    "Translation": ["translat", "multilingual", "deepl", "tmgmt"],
    "Safety & Governance": [
        "guardrail", "prompt safety", "pii", "moderation", "spam",
        "webform guard", "jailbreak", "prompt injection", "prompt manipulation",
        "governance", "compliance", "gdpr", "safety", "content security",
    ],
    "Accessibility": [
        "alt text", "alt-text", "wcag", "editoria11y", "accessibility", "a11y",
        "508", "screen reader", "aria-",
    ],
    "Developer Tools": [
        "upgrade assistant", "twig", "sdc", ".component.yml",
        "api normalization", "openapi", "self-heal", "development",
        "dev recipe", "drupaleasy", "debugger", "watchdog", "log analysis",
        "ecosystem", "installs and configures",
    ],
    "Evaluation & Testing": [
        "evaluat", "autoeval", "factuality", "grader", "prompt optimization",
    ],
}

# If any exclude term is present in the full text, the category is vetoed.
_EXCLUDES = {
    # Vector-DB providers and local runtimes are providers too, but they are
    # NOT cloud LLM/inference providers — keep them in their own buckets.
    "Cloud Providers": ["vdb", "vector"] + _LOCAL_RUNTIMES,
}

# Explicit machine-name → categories overrides. Highest precedence: when a bare
# machine name appears here, this list is used verbatim (no keyword matching).
# Use it for packages with null/empty descriptions (nothing to match on) and
# for any stubborn cases the keyword rules get wrong.
_OVERRIDES = {
    # Null-description packages (no text to classify from):
    "drup_aid": ["Tool"],
    "ai_audio_translate": ["Media", "Translation"],
    "quiz_questions_by_eca_and_ai": ["Content", "Automation"],
    # Packages the keyword rules miss (no distinctive keyword in their text):
    "auphonic": ["Media", "Cloud Providers"],
    "agui": ["Agents", "Tool"],
    "ai_context": ["Agents", "Tool"],
    "ai_validations": ["Content"],
}


def _category_matches(category: str, name_hay: str, full_hay: str) -> bool:
    """Return True if `category`'s rules match the given haystacks.

    name_hay = machine name + label (lowercased).
    full_hay = machine name + label + description (lowercased).
    """
    for term in _EXCLUDES.get(category, ()):
        if term in full_hay:
            return False
    for term in _KEYWORDS_NAME.get(category, ()):
        if term in name_hay:
            return True
    for term in _KEYWORDS_ANY.get(category, ()):
        if term in full_hay:
            return True
    return False


def detect_categories(label: str, description: str | None,
                      machine_name: str = "") -> list[str]:
    """Return the list of categories for one package.

    Multiple categories are allowed (e.g. a vector-DB provider is both
    'Vector Database' and 'Search'). Results follow CATEGORIES order so the
    list is stable and deduped. Returns [UNCATEGORIZED] when nothing matches.
    Tolerates description=None.
    """
    # machine_name is like 'drupal/ai_provider_openai' — take the bare name.
    bare = machine_name.rsplit("/", 1)[-1]

    if bare in _OVERRIDES:
        return list(_OVERRIDES[bare])

    # html.unescape() decodes entities (e.g. ai_chatblock's '&#039;') so the
    # keyword matching sees real characters. .lower() is PHP's strtolower().
    name_hay = html.unescape(f"{bare} {label}").lower()
    full_hay = html.unescape(f"{bare} {label} {description or ''}").lower()

    found = [c for c in CATEGORIES if _category_matches(c, name_hay, full_hay)]
    return found or [UNCATEGORIZED]


def apply_categories(payload: dict) -> None:
    """Add a 'categories' list to every module and recipe in a payload.

    Mutates the payload in place. Reads each row's already-resolved label,
    description and machine_name — so it works both on a freshly-built payload
    and on one loaded back from an existing results.json.
    """
    for entry in payload.get("modules", []) + payload.get("recipes", []):
        entry["categories"] = detect_categories(
            entry.get("label", ""),
            entry.get("description"),
            entry.get("machine_name", ""),
        )


def print_category_summary(payload: dict) -> None:
    """Print per-category counts and the Uncategorized list to stderr."""
    entries = payload.get("modules", []) + payload.get("recipes", [])
    counts: dict[str, int] = {}
    uncategorized: list[str] = []
    for entry in entries:
        cats = entry.get("categories", [])
        for cat in cats:
            counts[cat] = counts.get(cat, 0) + 1
        if cats == [UNCATEGORIZED]:
            uncategorized.append(entry.get("machine_name", "?"))

    print(f"\nCategorized {len(entries)} packages:", file=sys.stderr)
    for cat in CATEGORIES + [UNCATEGORIZED]:
        if cat in counts:
            print(f"  {counts[cat]:4d}  {cat}", file=sys.stderr)
    if uncategorized:
        print(f"\n{len(uncategorized)} uncategorized "
              f"(add a keyword or an override):", file=sys.stderr)
        for name in uncategorized:
            print(f"  - {name}", file=sys.stderr)


def recategorize_file(path: str) -> None:
    """Re-apply category rules to an existing results.json, in place.

    The fast, network-free path behind `--categorize FILE`. Everything the
    classifier needs is already in the file, so there's no crawl to do.
    """
    with open(path, encoding="utf-8") as fh:
        payload = json.load(fh)
    apply_categories(payload)
    with open(path, "w", encoding="utf-8") as fh:
        json.dump(payload, fh, indent=2, ensure_ascii=False)
    print_category_summary(payload)
    print(f"\nCategories written to {path}", file=sys.stderr)


# ---------------------------------------------------------------------------
# History — usage/downloads data points over time, for the renderers' Trend column
# ---------------------------------------------------------------------------
#
# A separate, ever-growing JSON file (default history.json), keyed by
# machine_name, recording one {date, usage} (modules) or {date, downloads}
# (recipes) entry per run. Kept apart from results.json (a point-in-time
# snapshot) since this is a time series instead. Nothing is ever pruned —
# a package that later disappears from results.json keeps its history, since
# it's still a real historical record. render_md.py / render_html.py read
# this file (independently — see their own copies of _trend()) to compute a
# Trend column comparing the two most recent data points for each package.

def load_history(path: str) -> dict:
    """Load history.json if it exists, else return an empty dict.

    A missing file just means this is the first run with history tracking —
    not an error condition.
    """
    try:
        with open(path, encoding="utf-8") as fh:
            return json.load(fh)
    except FileNotFoundError:
        return {}


def update_history(history: dict, payload: dict, today: str) -> dict:
    """Append today's usage/downloads data point for every module and recipe.

    Mutates and returns `history`. Modules contribute a "usage" entry,
    recipes a "downloads" entry — mirroring the fields already distinct
    between module and recipe rows in results.json. A machine_name missing
    from this run's payload (a package that dropped out of results.json)
    keeps its prior entries untouched — history is never pruned. A None
    value (that run's fetch failed) is still recorded as null rather than
    skipped: a gap is itself informative, not the same as no entry existing.
    """
    for entry in payload.get("modules", []):
        name = entry.get("machine_name")
        if not name:
            continue
        history.setdefault(name, []).append({"date": today, "usage": entry.get("usage")})
    for entry in payload.get("recipes", []):
        name = entry.get("machine_name")
        if not name:
            continue
        history.setdefault(name, []).append({"date": today, "downloads": entry.get("downloads")})
    return history


def save_history(path: str, history: dict) -> None:
    """Write history.json, same formatting convention as results.json."""
    with open(path, "w", encoding="utf-8") as fh:
        json.dump(history, fh, indent=2, ensure_ascii=False)


# ---------------------------------------------------------------------------
# Main
# ---------------------------------------------------------------------------

def main() -> None:
    """Entry point — parse arguments, orchestrate all steps, output results."""

    # argparse handles command-line argument parsing.
    # It's like PHP's getopt() but with automatic --help generation.
    # __doc__ is the module's docstring (the triple-quoted string at the top
    # of the file) — Python stores it automatically as a special variable.
    parser = argparse.ArgumentParser(
        description=__doc__,
        formatter_class=argparse.RawDescriptionHelpFormatter,
    )
    parser.add_argument(
        "--json", metavar="FILE",
        help="Write raw results to FILE as JSON. Omit to print JSON to stdout. "
             "Render with render_md.py / render_html.py — this script only collects data.",
    )
    parser.add_argument(
        "--categorize", metavar="FILE",
        help="Skip the network crawl: re-apply the category rules to an "
             "existing results.json (FILE) and write it back in place. Fast "
             "(no network) — use this to iterate on the category keyword rules.",
    )
    parser.add_argument(
        "--full-ecosystem", action="store_true",
        help="Widen the crawl beyond hard drupal/ai dependents: also include "
             "modules filed under drupal.org's 'Artificial Intelligence (AI)' "
             "project category, even when they have no composer dependency on "
             "drupal/ai. Off by default, in which case the results contain "
             "only projects that require drupal/ai.",
    )
    parser.add_argument(
        "--history", metavar="FILE", default="history.json",
        help="Append this run's usage (modules) / downloads (recipes) numbers, "
             "keyed by machine_name and dated, to FILE. Grows forever — every "
             "run's data point is kept, nothing pruned — so render_md.py and "
             "render_html.py can show a Trend column vs. the previous run. "
             "Written on every normal run, not with --categorize (which "
             "doesn't refetch data). Defaults to history.json.",
    )
    # args.json will be a filename string if provided, or None if omitted (print to stdout).
    # args.full_ecosystem is a bool — argparse converts the --full-ecosystem
    # flag's dash to an underscore.
    args = parser.parse_args()

    # --categorize is a fast, network-free path: re-derive categories on an
    # existing JSON file and exit. Everything the classifier needs (label,
    # description, machine name) is already in the file, so there's no crawl.
    if args.categorize:
        recategorize_file(args.categorize)
        return

    # -------------------------------------------------------------------------
    # Step 1: Collect candidate module names from the enabled sources
    # -------------------------------------------------------------------------
    # The first two sources always run and feed the strict "must require
    # drupal/ai" verification. The third (drupal.org's AI project category)
    # only runs under --full-ecosystem, because it deliberately admits
    # modules that have no drupal/ai dependency at all.
    full_ecosystem = args.full_ecosystem
    source_count = 3 if full_ecosystem else 2
    print("Collecting candidates …", file=sys.stderr)

    # `file=sys.stderr` sends output to STDERR instead of STDOUT.
    # This keeps progress messages separate from the markdown output,
    # so piping or redirecting stdout only captures the final table.
    print(f"  [1/{source_count}] Scraping AI ecosystem pages …", file=sys.stderr)
    eco_names = get_ecosystem_candidates()
    print(f"        {len(eco_names)} names found", file=sys.stderr)
    # len() is like PHP's count().

    print(f"  [2/{source_count}] Searching packages.drupal.org for 'ai' …", file=sys.stderr)
    search_names = get_search_candidates("ai")
    print(f"        {len(search_names)} names found", file=sys.stderr)

    if full_ecosystem:
        print(f"  [3/{source_count}] Listing modules in drupal.org's AI project category …",
              file=sys.stderr)
        tagged_names = get_tagged_candidates()
        print(f"        {len(tagged_names)} names found", file=sys.stderr)
    else:
        # Skipped entirely rather than crawled-and-filtered: this source adds
        # ~300 candidates, and every one of them would need its own p2 request
        # only to be rejected by the drupal/ai check.
        tagged_names = []
        print("  Skipping drupal.org AI project category "
              "(pass --full-ecosystem to include it)", file=sys.stderr)

    # Modules from the AI category source qualify by tag alone — they are
    # exempt from the drupal/ai `require` check during verification below.
    # Empty without --full-ecosystem, so every candidate is checked strictly.
    tagged_set = set(tagged_names)

    # Union-merge: start with ecosystem names (preserving their order),
    # then append any search/tagged names not already in the set.
    # set(eco_names) builds a set from a list in one step — like array_flip()
    # in PHP to create an O(1) lookup table.
    seen: set[str] = set(eco_names)
    all_candidates = list(eco_names)  # list() copies the list
    for n in search_names + tagged_names:
        if n not in seen:
            seen.add(n)
            all_candidates.append(n)
    print(f"  {len(all_candidates)} unique candidates after merge\n", file=sys.stderr)

    # -------------------------------------------------------------------------
    # Step 2: Verify each candidate against packages.drupal.org
    # -------------------------------------------------------------------------
    # Phase 2a: sequential p2 fetches — one request at a time, single thread.
    print("Verifying via packages.drupal.org p2 files …", file=sys.stderr)

    rows          = []   # will hold one dict per confirmed module
    skipped       = 0    # count of candidates that failed verification
    date_fallbacks = 0   # count of modules that needed the updates.drupal.org fallback
    total         = len(all_candidates)
    p2_map        = {}   # machine_name → p2 result dict (only passing modules)

    for done, name in enumerate(all_candidates, 1):
        try:
            result = get_p2_info(name, require_ai=name not in tagged_set)
        except Exception as exc:
            print(f"  [{done}/{total}] {name}: error — {exc}", file=sys.stderr)
            result = None
        if result is not None:
            p2_map[name] = result
            print(f"  [{done}/{total}] {name}: ok", file=sys.stderr)
        else:
            skipped += 1
            print(f"  [{done}/{total}] {name}: skip", file=sys.stderr)

    # Phase 2b: sequential info.yml label fetches for verified modules only.
    print("\nFetching module labels + descriptions from info.yml files …", file=sys.stderr)
    verified = [(n, p2_map[n]) for n in all_candidates if n in p2_map]
    label_map = {}  # machine_name → label (only when found)
    desc_map  = {}  # machine_name → description from info.yml (only when found)

    for done, (name, p2) in enumerate(verified, 1):
        try:
            label, description = get_module_label_and_description(name, p2["version"])
        except Exception as exc:
            print(f"  [{done}/{len(verified)}] {name}: error — {exc}", file=sys.stderr)
            label = description = None
        if label:
            label_map[name] = label
        if description:
            desc_map[name] = description
        print(f"  [{done}/{len(verified)}] {name}: {label or '(using fallback name)'}"
              f"{' +desc' if description else ''}", file=sys.stderr)

    # Phase 2c: sequential usage + security coverage fetches for verified
    # modules only. Preserves all_candidates order (ecosystem-first) for
    # deterministic output.
    print("\nFetching usage counts …", file=sys.stderr)
    for i, (machine_name, p2) in enumerate(verified, 1):
        date = p2["date"]
        if not date:
            date = get_release_date_fallback(machine_name, p2["version"])
            date_fallbacks += 1
            if date:
                print(f"  [{i}/{len(verified)}] {machine_name}: date from updates.drupal.org", file=sys.stderr)

        usage, security_covered = get_usage_and_security(machine_name)
        print(f"  [{i}/{len(verified)}] {machine_name}: usage={usage} security_covered={security_covered}", file=sys.stderr)

        rows.append({
            "machine_name":      p2["composer_name"] or f"drupal/{machine_name}",
            "label":             label_map.get(machine_name) or human_name(machine_name),
            "description":       desc_map.get(machine_name),
            "url":               DRUPAL_PROJECT_URL.format(name=machine_name),
            "version":           p2["version"],
            "release_date":      date or "—",
            "usage":             usage,
            "security_covered":  security_covered,
            "stability":         detect_stability(p2["version"]),
            # False only for AI-category-tagged modules whose latest release
            # has no drupal/ai in its composer require.
            "requires_ai":       p2["ai_constraint"] is not None,
        })

    print(
        f"\nResults: {len(rows)} confirmed modules "
        f"({skipped} skipped, {date_fallbacks} used date fallback)",
        file=sys.stderr,
    )

    # -------------------------------------------------------------------------
    # Step 1b: Collect candidate recipe names (3 sources, merged)
    # -------------------------------------------------------------------------
    # Recipes are verified separately from modules (different registry, no
    # usage/security data), so they get their own candidate-discovery step.
    print("\nCollecting recipe candidates …", file=sys.stderr)

    print("  [1/3] Reusing AI ecosystem names not matched as modules …", file=sys.stderr)
    eco_unmatched = [n for n in eco_names if n not in p2_map]
    print(f"        {len(eco_unmatched)} names", file=sys.stderr)

    print("  [2/3] Searching Packagist for type=drupal-recipe, q=ai …", file=sys.stderr)
    recipe_search_names = get_recipe_search_candidates()
    print(f"        {len(recipe_search_names)} names found", file=sys.stderr)

    print("  [3/3] Fetching curated AI recipe list …", file=sys.stderr)
    curated_recipe_names = get_curated_recipe_candidates()
    print(f"        {len(curated_recipe_names)} names found", file=sys.stderr)

    seen_recipes: set[str] = set()
    all_recipe_candidates = []
    for n in eco_unmatched + recipe_search_names + curated_recipe_names:
        if n not in seen_recipes:
            seen_recipes.add(n)
            all_recipe_candidates.append(n)
    print(f"  {len(all_recipe_candidates)} unique recipe candidates after merge\n", file=sys.stderr)

    # -------------------------------------------------------------------------
    # Step 2d: Verify each recipe candidate against repo.packagist.org
    # -------------------------------------------------------------------------
    print("Verifying recipes via repo.packagist.org p2 files …", file=sys.stderr)

    recipe_rows    = []
    recipe_skipped = 0
    recipe_total   = len(all_recipe_candidates)
    recipe_p2_map  = {}

    for done, name in enumerate(all_recipe_candidates, 1):
        try:
            result = get_recipe_info(name)
        except Exception as exc:
            print(f"  [{done}/{recipe_total}] {name}: error — {exc}", file=sys.stderr)
            result = None
        if result is not None:
            recipe_p2_map[name] = result
            print(f"  [{done}/{recipe_total}] {name}: ok", file=sys.stderr)
        else:
            recipe_skipped += 1
            print(f"  [{done}/{recipe_total}] {name}: skip", file=sys.stderr)

    # Phase 2e: sequential recipe.yml label + description fetches for verified
    # recipes only.
    print("\nFetching recipe labels + descriptions from recipe.yml files …", file=sys.stderr)
    verified_recipes = [(n, recipe_p2_map[n]) for n in all_recipe_candidates if n in recipe_p2_map]
    recipe_label_map = {}
    recipe_desc_map  = {}  # machine_name → description from recipe.yml (only when found)

    for done, (name, info) in enumerate(verified_recipes, 1):
        try:
            label, description = get_recipe_label_and_description(name, info["version"])
        except Exception as exc:
            print(f"  [{done}/{len(verified_recipes)}] {name}: error — {exc}", file=sys.stderr)
            label = description = None
        if label:
            recipe_label_map[name] = label
        if description:
            recipe_desc_map[name] = description
        print(f"  [{done}/{len(verified_recipes)}] {name}: {label or '(using fallback name)'}"
              f"{' +desc' if description else ''}", file=sys.stderr)

    # Phase 2f: sequential recipe stats fetches from packagist.org.
    # Downloads and stars (favers) come from the same API response, so one
    # request per recipe covers both.
    print("\nFetching recipe stats (downloads + stars) from Packagist …", file=sys.stderr)
    recipe_stats_map = {}  # machine_name → (downloads, stars)

    for done, (name, _) in enumerate(verified_recipes, 1):
        try:
            dl, stars = get_recipe_stats(name)
        except Exception as exc:
            print(f"  [{done}/{len(verified_recipes)}] {name}: error — {exc}", file=sys.stderr)
            dl = stars = None
        recipe_stats_map[name] = (dl, stars)
        print(f"  [{done}/{len(verified_recipes)}] {name}: downloads={dl} stars={stars}", file=sys.stderr)

    for machine_name, info in verified_recipes:
        dl, stars = recipe_stats_map.get(machine_name, (None, None))
        recipe_rows.append({
            "machine_name": info["composer_name"] or f"drupal/{machine_name}",
            "label":        recipe_label_map.get(machine_name) or human_name(machine_name),
            "description":  recipe_desc_map.get(machine_name),
            "url":          DRUPAL_PROJECT_URL.format(name=machine_name),
            "version":      info["version"],
            "release_date": info["date"] or "—",
            "stability":    detect_stability(info["version"]),
            "downloads":    dl,
            "stars":        stars,
        })

    # Sort by Packagist downloads descending (None/0 last), then alphabetically.
    recipe_rows.sort(key=lambda r: (-(r["downloads"] or 0), r["label"].lower()))

    print(
        f"\nResults: {len(recipe_rows)} confirmed recipes ({recipe_skipped} skipped)",
        file=sys.stderr,
    )

    # -------------------------------------------------------------------------
    # Per-source discovery summary
    # -------------------------------------------------------------------------
    eco_set    = set(eco_names)
    search_set = set(search_names)
    mod_eco    = sum(1 for n in p2_map if n in eco_set)
    mod_search = sum(1 for n in p2_map if n in search_set)
    mod_tagged = sum(1 for n in p2_map if n in tagged_set)
    mod_tag_only = sum(1 for n in p2_map
                       if n in tagged_set and n not in eco_set and n not in search_set)
    mod_both   = sum(1 for n in p2_map if n in eco_set and n in search_set)

    eco_unmatched_set = set(eco_unmatched)
    rec_search_set    = set(recipe_search_names)
    curated_set       = set(curated_recipe_names)
    rec_eco     = sum(1 for n in recipe_p2_map if n in eco_unmatched_set)
    rec_search  = sum(1 for n in recipe_p2_map if n in rec_search_set)
    rec_curated = sum(1 for n in recipe_p2_map if n in curated_set)
    rec_overlap = rec_eco + rec_search + rec_curated - len(recipe_p2_map)

    print("\nConfirmed by source:", file=sys.stderr)
    print(f"  Modules ({len(rows)}):", file=sys.stderr)
    print(f"    ecosystem page:              {mod_eco:3}  ({mod_eco - mod_both} unique to this source)", file=sys.stderr)
    print(f"    packages.drupal.org search:  {mod_search:3}  ({mod_search - mod_both} unique to this source)", file=sys.stderr)
    if full_ecosystem:
        print(f"    drupal.org AI category tag:  {mod_tagged:3}  ({mod_tag_only} unique to this source)", file=sys.stderr)
    if mod_both:
        print(f"    in both eco + search:        {mod_both:3}", file=sys.stderr)
    print(f"  Recipes ({len(recipe_rows)}):", file=sys.stderr)
    print(f"    ecosystem (unmatched modules): {rec_eco:3}", file=sys.stderr)
    print(f"    Packagist type+keyword search: {rec_search:3}", file=sys.stderr)
    print(f"    curated AI recipe list:        {rec_curated:3}", file=sys.stderr)
    if rec_overlap > 0:
        print(f"    ({rec_overlap} recipe(s) counted in multiple sources above)", file=sys.stderr)

    # -------------------------------------------------------------------------
    # Step 3: Sort results by usage count, highest first
    # -------------------------------------------------------------------------

    # list.sort() sorts in place (modifies the list). The `key` argument
    # accepts a function that extracts the sort value from each element —
    # like PHP's usort() with a comparison callback.
    # `lambda r: ...` is an anonymous function — like PHP's fn($r) => ...
    # Modules with no usage data (None) sort to the bottom via the -1 fallback.
    rows.sort(key=lambda r: r["usage"] if r["usage"] else -1, reverse=True)

    # -------------------------------------------------------------------------
    # Step 4: Write or print JSON
    # -------------------------------------------------------------------------
    # This script only collects and verifies data — rendering is render_md.py's
    # and render_html.py's job. Keeping JSON as the single output format means
    # those two renderers (which get updated when columns/filters change) are
    # never at risk of drifting out of sync with a third, inline renderer here.

    payload = {
        "generated":       datetime.now().strftime("%Y-%m-%d"),
        "drupal_versions": sorted(TARGET_VERSIONS),
        # Records which crawl produced this file, so the renderers can state
        # the scope accurately (and know whether the drupal/ai column is
        # meaningful — without --full-ecosystem every row requires drupal/ai).
        "full_ecosystem":  full_ecosystem,
        "modules":         rows,
        "recipes":         recipe_rows,
    }

    # Tag every module and recipe with its categories (derived from label,
    # description and machine name). Pure text processing — no extra requests.
    apply_categories(payload)

    # Append this run's usage/downloads data point to history.json (unbounded
    # retention — see the History section above). Unconditional on every
    # normal run, unlike --json which is optional.
    history = load_history(args.history)
    update_history(history, payload, payload["generated"])
    save_history(args.history, history)

    if args.json:
        with open(args.json, "w", encoding="utf-8") as fh:
            json.dump(payload, fh, indent=2, ensure_ascii=False)
        print(f"JSON written to {args.json}", file=sys.stderr)
    else:
        # print() with no file argument writes to STDOUT, so it can be
        # cleanly piped or redirected: python3 script.py > results.json
        print(json.dumps(payload, indent=2, ensure_ascii=False))


# This block only runs when the script is executed directly:
#   python3 drupal_ai_dependents.py
# It does NOT run when another Python file imports this one.
# The PHP equivalent would be checking if the file is the entry point
# (there's no direct PHP equivalent, but it's similar to Symfony's
# public/index.php being the only file that should bootstrap the kernel).
if __name__ == "__main__":
    main()

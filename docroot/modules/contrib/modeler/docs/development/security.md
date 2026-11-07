# Security

The modeler implements multiple security layers to protect against common web
vulnerabilities. For the complete reference, see `ui/docs/security.md`.

## XSS prevention

All user-provided content is sanitized before rendering:

- **HTML sanitization**: All HTML content passes through `sanitizeHtml()` or
  `sanitizeTokenHtml()` (powered by DOMPurify).
- **Safe DOM methods**: `textContent` is used instead of `innerHTML` wherever
  possible.
- **Token sanitization**: Token values from internal token reordering (dragging
  a token pill within a field) are validated and sanitized before insertion.

### Rules

- Never use `innerHTML` directly. Always use `sanitizeHtml()`.
- Never use `dangerouslySetInnerHTML` without sanitization.
- All markup fields from the backend are sanitized before rendering.

## CSRF protection

All state-changing API calls (save, config form, replay, test) include a
CSRF token:

1. The frontend fetches a token from Drupal's `/session/token` endpoint.
2. The token is sent as an `X-CSRF-Token` header on every API request.
3. The backend validates the token before processing the request.

Use `fetchValidatedCsrfToken()` from `utils/` for all API calls.

## Input validation

All external data is validated before use:

- **API responses**: Validated through `utils/validation.ts`.
- **Replay entries**: Each entry is checked with `validateReplayEntries()`;
  invalid entries are dropped with warnings.
- **Paste content**: HTML paste events are sanitized to prevent injection.
- **URL validation**: All URLs are validated before use.

## Clipboard security

Copied workflow data is stored with encrypted references to prevent tampering
when pasting between browser sessions.

## Security checklist for contributors

- [ ] All HTML content sanitized with `sanitizeHtml()`.
- [ ] CSRF tokens included in all API calls.
- [ ] External data validated through `utils/validation.ts`.
- [ ] No `eval()`, `Function()`, or dynamic script loading.
- [ ] Internal token reordering values properly validated.
- [ ] Error messages do not leak sensitive information.
- [ ] Form values are properly escaped before sending to backend.

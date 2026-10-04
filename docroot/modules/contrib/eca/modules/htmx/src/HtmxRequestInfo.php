<?php

declare(strict_types=1);

namespace Drupal\eca_htmx;

use Drupal\Core\Htmx\HtmxRequestInfoTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Reads HTMX request information from the current request.
 *
 * This is a thin, reusable wrapper around the Drupal core
 * \Drupal\Core\Htmx\HtmxRequestInfoTrait. The trait declares an abstract
 * getRequest() method; this class satisfies it from the injected request
 * stack so that ECA conditions and the token data provider can share a single
 * implementation instead of each re-implementing the trait.
 *
 * The trait's helper methods are protected, so this class re-exposes the ones
 * ECA needs as public methods. We deliberately route everything through core's
 * trait rather than reading the HX-* headers by hand. The documented
 * exceptions are trigger(), source() and requestType(), which cover accessors
 * that do not exist in both supported core majors; see those methods for why
 * they cannot call the accessor.
 *
 * The reason is the same in all three cases. A literal call to an accessor
 * that only one major declares cannot pass static analysis against the other
 * one, and suppressing that would leave an unmatched - and non-ignorable -
 * suppression behind on the version where the method does exist. Reading the
 * header directly is well defined on both.
 *
 * Those three are the only exceptions. Every other accessor here, target()
 * included, calls the trait unguarded, because core declares it on both
 * supported majors: unlike getHtmxTrigger() and getHtmxPrompt(), neither
 * getHtmxTarget() nor getHtmxTriggerName() is deprecated, and both read a
 * header that htmx 4 still sends. The asymmetry is deliberate; see target()
 * and triggerName() for the detail.
 */
class HtmxRequestInfo {

  use HtmxRequestInfoTrait;

  /**
   * Constructs a new HtmxRequestInfo object.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The request stack.
   */
  public function __construct(
    protected RequestStack $requestStack,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  protected function getRequest(): Request {
    return $this->requestStack->getCurrentRequest() ?? new Request();
  }

  /**
   * Determines whether the current request was sent by HTMX.
   *
   * @return bool
   *   TRUE if the 'HX-Request' header is present.
   */
  public function isRequest(): bool {
    return $this->isHtmxRequest();
  }

  /**
   * Determines whether the current request was boosted by HTMX.
   *
   * @return bool
   *   TRUE if the 'HX-Boosted' header is present.
   */
  public function isBoosted(): bool {
    return $this->isHtmxBoosted();
  }

  /**
   * Determines whether the current request is for history restoration.
   *
   * @return bool
   *   TRUE if the 'HX-History-Restore-Request' header is present.
   */
  public function isHistoryRestore(): bool {
    return $this->isHtmxHistoryRestoration();
  }

  /**
   * Retrieves the target identifier from the HTMX request header.
   *
   * This deliberately carries no version guard, unlike trigger(), source()
   * and requestType(). Core deprecated getHtmxTrigger() and getHtmxPrompt()
   * in drupal:11.5.0 for removal in drupal:12.0.0 but left getHtmxTarget()
   * untouched, and 'HX-Target' is still a documented htmx 4 header. Accessor
   * and header both span the htmx 3 to htmx 4 boundary, so the plain call
   * below is correct on both supported majors.
   *
   * What htmx puts in the header did change, though, and ECA passes it
   * through as sent rather than normalizing it:
   * - htmx 3 sends the id of the target element, e.g. 'results'.
   * - htmx 4 sends the tag and id together, e.g. 'div#results', or just the
   *   tag name when the element has no id, e.g. 'div'.
   *
   * A version guard would not help with that, since both majors reach the
   * value through the same accessor and the same header.
   *
   * @return string
   *   The value of the 'HX-Target' header, or an empty string if not set.
   *
   * @see \Drupal\Core\Htmx\HtmxRequestInfoTrait::getHtmxTarget()
   * @see https://htmx.org/reference/#request_headers
   * @see https://four.htmx.org/reference/headers/HX-Target
   * @see https://www.drupal.org/node/3583674
   * @see https://git.drupalcode.org/project/eca/-/work_items/3590445
   */
  public function target(): string {
    return $this->getHtmxTarget();
  }

  /**
   * Retrieves the trigger identifier from the HTMX request header.
   *
   * Core changed both the accessor and the header it reads with htmx 4, and
   * neither accessor exists in both major versions:
   * - Core 11 provides getHtmxTrigger(), reading 'HX-Trigger'.
   * - Core 12 replaced it with getHtmxSource(), reading 'HX-Source' and
   *   passing the value through rawurldecode() because htmx 4 encodes the
   *   header with encodeURI().
   *
   * ECA supports both majors from a single code base, so this method reads the
   * headers directly instead of calling either accessor.
   *
   * The value format therefore differs between the majors, and ECA does not
   * paper over that:
   * - Core 11 returns the element id, e.g. 'my-button', and an empty string
   *   when the triggering element has no id.
   * - Core 12 returns the CSS selector built by Drupal's htmx extension, e.g.
   *   'button[name="my_name"]', 'button[data-drupal-selector="edit-submit"]',
   *   'button#my-button' or a bare 'button'.
   *
   * Recovering the old id semantics on core 12 by parsing '#id' out of the
   * selector was considered and rejected, because it cannot work. Drupal's
   * drupalIdentifier() picks the first available of name, data-drupal-selector
   * and id, so an element carrying both a name and an id never yields the id
   * at all - and Drupal form buttons almost always carry a name. Parsing would
   * therefore return an empty string for exactly the elements that models
   * match on today, which is worse than an honestly changed value.
   *
   * Use source() when the selector itself is wanted, and triggerName() when
   * the name attribute is wanted; the latter is stable across both majors.
   *
   * @return string
   *   The trigger identifier, or an empty string if not set.
   *
   * @see \Drupal\eca_htmx\HtmxRequestInfo::source()
   * @see \Drupal\Core\Htmx\HtmxRequestInfoTrait::getHtmxSource()
   * @see https://git.drupalcode.org/project/drupal/-/blob/main/core/misc/htmx/htmx-assets.js
   * @see https://www.drupal.org/node/3583674
   * @see https://git.drupalcode.org/project/eca/-/work_items/3590437
   */
  public function trigger(): string {
    if (version_compare(\Drupal::VERSION, '12.0-dev', '>=')) {
      return rawurldecode($this->getRequest()->headers->get('HX-Source', ''));
    }
    return $this->getRequest()->headers->get('HX-Trigger', '');
  }

  /**
   * Retrieves the CSS selector of the element that triggered the request.
   *
   * This is the raw 'HX-Source' value that htmx 4 sends, decoded. Drupal
   * builds it from the first available property of the triggering element, in
   * this order:
   * - a name attribute, e.g. 'button[name="first_item"]'
   * - a data-drupal-selector, e.g. 'button[data-drupal-selector="first_item"]'
   * - an id, e.g. 'button#id-value'
   * - otherwise the bare tag name, e.g. 'button'
   *
   * Core 11 ships htmx 3, which sends no such header. This returns an empty
   * string there rather than synthesizing a selector from 'HX-Trigger', so
   * that a model can tell "no selector was sent" from "a selector was sent".
   *
   * Reads the header directly for the reason given on the class docblock:
   * getHtmxSource() does not exist on core 11.
   *
   * @return string
   *   The trigger source selector, or an empty string if not set or if the
   *   running core does not send the header.
   *
   * @see \Drupal\Core\Htmx\HtmxRequestInfoTrait::getHtmxSource()
   * @see https://git.drupalcode.org/project/drupal/-/blob/main/core/misc/htmx/htmx-assets.js
   */
  public function source(): string {
    if (version_compare(\Drupal::VERSION, '12.0-dev', '>=')) {
      return rawurldecode($this->getRequest()->headers->get('HX-Source', ''));
    }
    return '';
  }

  /**
   * Retrieves whether the request targets a full page or a fragment.
   *
   * The 'HX-Request-Type' header that htmx 4 sends carries 'full' when the
   * target is the document body or a selection is in play, and 'partial'
   * otherwise.
   *
   * Core 11 ships htmx 3, which sends no such header, so this returns an empty
   * string there.
   *
   * Reads the header directly for the reason given on the class docblock:
   * getHtmxRequestType() does not exist on core 11.
   *
   * @return string
   *   'full', 'partial', or an empty string if not set or if the running core
   *   does not send the header.
   *
   * @see \Drupal\Core\Htmx\HtmxRequestInfoTrait::getHtmxRequestType()
   * @see https://four.htmx.org/reference/headers/HX-Request-Type
   */
  public function requestType(): string {
    if (version_compare(\Drupal::VERSION, '12.0-dev', '>=')) {
      return $this->getRequest()->headers->get('HX-Request-Type', '');
    }
    return '';
  }

  /**
   * Retrieves the name attribute of the element that triggered the request.
   *
   * This is stable across both majors, which is why it is the recommended way
   * to identify a triggering element in a model. Core 11 reads it from the
   * 'HX-Trigger-Name' header; core 12 extracts the name="..." portion out of
   * 'HX-Source'. Either way an element without a name attribute yields an
   * empty string. Both paths live in core's trait, so no version guard is
   * needed here.
   *
   * @return string
   *   The name attribute of the triggering element, or an empty string if it
   *   has none.
   *
   * @see \Drupal\Core\Htmx\HtmxRequestInfoTrait::getHtmxTriggerName()
   */
  public function triggerName(): string {
    return $this->getHtmxTriggerName();
  }

  /**
   * Retrieves the URL of the requesting page from the HTMX request header.
   *
   * @return string
   *   The value of the 'HX-Current-URL' header, or an empty string if not set.
   */
  public function currentUrl(): string {
    return $this->getHtmxCurrentUrl();
  }

}

import { useEffect, useRef, useState, useCallback } from 'react';

// Store original messages-list location for restoration
interface MessagesListContext {
  element: HTMLElement;
  originalParent: HTMLElement;
  originalNextSibling: ChildNode | null;
  observer?: MutationObserver;
}

interface UseMessagesContainerResult {
  messagesContainerRef: React.RefObject<HTMLDivElement | null>;
  messagesVisible: boolean;
  hasMessages: boolean;
  handleToggleMessages: () => void;
  handleClearMessages: () => void;
}

const MESSAGES_FADE_DELAY = 5000; // 5 seconds

export function useMessagesContainer(): UseMessagesContainerResult {
  const messagesContainerRef = useRef<HTMLDivElement>(null);
  const messagesListContextRef = useRef<MessagesListContext | null>(null);
  const messagesFadeTimerRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  const [messagesVisible, setMessagesVisible] = useState(true);
  const [hasMessages, setHasMessages] = useState(false);
  const messagesPinnedRef = useRef(false);
  const messagesVisibleRef = useRef(true);

  // Toggle messages visibility with smart behavior:
  // - If hidden: show and pin (no auto-fade)
  // - If visible: hide immediately and unpin
  const handleToggleMessages = useCallback(() => {
    setMessagesVisible(prev => {
      const next = !prev;
      messagesVisibleRef.current = next;

      if (prev) {
        // Messages are visible - hide them
        messagesPinnedRef.current = false;
      } else {
        // Messages are hidden - show and pin (no auto-fade)
        messagesPinnedRef.current = true;
      }

      // Clear any existing fade timer
      if (messagesFadeTimerRef.current) {
        clearTimeout(messagesFadeTimerRef.current);
        messagesFadeTimerRef.current = null;
      }

      return next;
    });
  }, []);

  // Clear all messages via Drupal.Message and reset state
  const handleClearMessages = useCallback(() => {
    // Clear via Drupal's message API
    if (typeof Drupal !== 'undefined' && Drupal.Message) {
      try {
        const messenger = new Drupal.Message();
        messenger.clear();
      } catch {
        // Silently fail if Drupal.Message is not available
      }
    }

    // Also clear the DOM directly in case Drupal.Message didn't handle it
    const messagesList = messagesListContextRef.current?.element;
    if (messagesList) {
      messagesList.innerHTML = '';
    }

    // Reset state
    messagesPinnedRef.current = false;
    messagesVisibleRef.current = false;
    setMessagesVisible(false);
    setHasMessages(false);

    // Clear any existing fade timer
    if (messagesFadeTimerRef.current) {
      clearTimeout(messagesFadeTimerRef.current);
      messagesFadeTimerRef.current = null;
    }
  }, []);

  // Move messages-list into the container on mount, restore on unmount
  useEffect(() => {
    const messagesList = document.querySelector('.messages-list') as HTMLElement | null;
    const messagesContainer = messagesContainerRef.current;
    let hadContentPreviously = false;

    if (messagesList && messagesContainer) {
      // Store original location
      const originalParent = messagesList.parentElement;
      if (originalParent) {
        messagesListContextRef.current = {
          element: messagesList,
          originalParent: originalParent,
          originalNextSibling: messagesList.nextSibling
        };

        // Move messages-list into our container
        messagesContainer.appendChild(messagesList);

        // Check if there are any messages
        const checkMessages = (mutations?: MutationRecord[]) => {
          const hasContent = messagesList.children.length > 0 || messagesList.textContent?.trim();
          const hasContentNow = !!hasContent;

          setHasMessages(hasContentNow);

          // Determine if new content was added
          const isNewContent = hasContentNow && !hadContentPreviously;
          const hasAddedNodes = !!mutations?.some(m => m.addedNodes.length > 0);

          // Re-show messages when:
          // 1. Content transitions from empty to having content (first messages), OR
          // 2. New nodes are added while the container has faded out (invisible)
          if (isNewContent || (hasAddedNodes && !messagesVisibleRef.current)) {
            // Show messages when new content arrives
            messagesVisibleRef.current = true;
            setMessagesVisible(true);

            // Clear existing timer
            if (messagesFadeTimerRef.current) {
              clearTimeout(messagesFadeTimerRef.current);
            }

            // Only auto-hide if messages are not pinned
            if (!messagesPinnedRef.current) {
              // Auto-hide after delay
              messagesFadeTimerRef.current = setTimeout(() => {
                messagesVisibleRef.current = false;
                setMessagesVisible(false);
              }, MESSAGES_FADE_DELAY);
            }
          }

          hadContentPreviously = hasContentNow;
        };

        // Initial check
        checkMessages();

        // Watch for changes to messages-list
        const observer = new MutationObserver((mutations) => checkMessages(mutations));
        observer.observe(messagesList, {
          childList: true,
          subtree: true,
          characterData: true
        });

        // Store observer for cleanup
        messagesListContextRef.current.observer = observer;
      }
    }

    // Cleanup: restore messages-list to original location
    return () => {
      // Clear fade timer
      if (messagesFadeTimerRef.current) {
        clearTimeout(messagesFadeTimerRef.current);
        messagesFadeTimerRef.current = null;
      }

      const context = messagesListContextRef.current;
      if (context) {
        // Disconnect observer
        if (context.observer) {
          context.observer.disconnect();
        }

        if (context.element && context.originalParent) {
          try {
            // Restore to original position
            const nextSibling = context.originalNextSibling;
            if (nextSibling && context.originalParent.contains(nextSibling as globalThis.Node)) {
              context.originalParent.insertBefore(context.element, nextSibling);
            } else {
              context.originalParent.appendChild(context.element);
            }
          } catch {
            // If restoration fails (e.g., parent no longer exists), append to body as fallback
            document.body.appendChild(context.element);
          }
        }
        messagesListContextRef.current = null;
      }
    };
  }, []);

  return {
    messagesContainerRef,
    messagesVisible,
    hasMessages,
    handleToggleMessages,
    handleClearMessages
  };
}

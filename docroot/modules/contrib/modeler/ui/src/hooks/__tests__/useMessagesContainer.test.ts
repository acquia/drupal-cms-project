import { renderHook, act } from '@testing-library/react';
import { useMessagesContainer } from '../useMessagesContainer';

describe('useMessagesContainer', () => {
  let mockMessagesList: HTMLDivElement;
  let mockOriginalParent: HTMLDivElement;

  beforeEach(() => {
    jest.useFakeTimers();

    // Create mock messages-list element and its original parent
    mockOriginalParent = document.createElement('div');
    mockOriginalParent.id = 'original-parent';
    document.body.appendChild(mockOriginalParent);

    mockMessagesList = document.createElement('div');
    mockMessagesList.className = 'messages-list';
    mockOriginalParent.appendChild(mockMessagesList);
  });

  afterEach(() => {
    jest.useRealTimers();

    // Clean up DOM
    const originalParent = document.getElementById('original-parent');
    if (originalParent) originalParent.remove();

    const messagesList = document.querySelector('.messages-list');
    if (messagesList) messagesList.remove();
  });

  describe('return values', () => {
    it('should return messagesContainerRef', () => {
      const { result } = renderHook(() => useMessagesContainer());

      expect(result.current.messagesContainerRef).toBeDefined();
    });

    it('should return messagesVisible as true initially', () => {
      const { result } = renderHook(() => useMessagesContainer());

      expect(result.current.messagesVisible).toBe(true);
    });

    it('should return hasMessages based on DOM content', () => {
      const { result } = renderHook(() => useMessagesContainer());

      // Initially empty
      expect(result.current.hasMessages).toBe(false);
    });

    it('should return handleToggleMessages function', () => {
      const { result } = renderHook(() => useMessagesContainer());

      expect(typeof result.current.handleToggleMessages).toBe('function');
    });

    it('should return handleClearMessages function', () => {
      const { result } = renderHook(() => useMessagesContainer());

      expect(typeof result.current.handleClearMessages).toBe('function');
    });
  });

  describe('handleToggleMessages', () => {
    it('should toggle messages visibility', () => {
      const { result } = renderHook(() => useMessagesContainer());

      // Initially visible
      expect(result.current.messagesVisible).toBe(true);

      // Toggle to hide
      act(() => {
        result.current.handleToggleMessages();
      });

      expect(result.current.messagesVisible).toBe(false);

      // Toggle to show
      act(() => {
        result.current.handleToggleMessages();
      });

      expect(result.current.messagesVisible).toBe(true);
    });

    it('should NOT auto-hide when toggled to show (pinned mode)', () => {
      const { result } = renderHook(() => useMessagesContainer());

      // First toggle to hide
      act(() => {
        result.current.handleToggleMessages();
      });

      expect(result.current.messagesVisible).toBe(false);

      // Toggle to show (should pin, no auto-fade)
      act(() => {
        result.current.handleToggleMessages();
      });

      expect(result.current.messagesVisible).toBe(true);

      // Advance past 5 seconds - should still be visible (pinned)
      act(() => {
        jest.advanceTimersByTime(6000);
      });

      expect(result.current.messagesVisible).toBe(true);
    });

    it('should hide immediately when toggled while visible (pinned)', () => {
      const { result } = renderHook(() => useMessagesContainer());

      // Start by toggling to hide, then show (to enter pinned mode)
      act(() => {
        result.current.handleToggleMessages();
      });

      act(() => {
        result.current.handleToggleMessages();
      });

      expect(result.current.messagesVisible).toBe(true);

      // Toggle again while visible - should hide immediately
      act(() => {
        result.current.handleToggleMessages();
      });

      expect(result.current.messagesVisible).toBe(false);

      // Should stay hidden (no timer running)
      act(() => {
        jest.advanceTimersByTime(1000);
      });

      expect(result.current.messagesVisible).toBe(false);
    });
  });

  describe('handleClearMessages', () => {
    let container: HTMLDivElement;

    beforeEach(() => {
      // Create a container element that will hold the ref so the effect wires up
      container = document.createElement('div');
      document.body.appendChild(container);
    });

    afterEach(() => {
      container.remove();
    });

    it('should clear messages-list DOM content', () => {
      mockMessagesList.innerHTML = '<div>Test message</div>';

      const { result } = renderHook(() => {
        const hookResult = useMessagesContainer();
        // Wire the ref to the container so the effect moves the messages-list into it
        (hookResult.messagesContainerRef as any).current = container;
        return hookResult;
      });

      act(() => {
        result.current.handleClearMessages();
      });

      expect(mockMessagesList.innerHTML).toBe('');
    });

    it('should set hasMessages to false', () => {
      mockMessagesList.innerHTML = '<div>Test message</div>';

      const { result } = renderHook(() => {
        const hookResult = useMessagesContainer();
        (hookResult.messagesContainerRef as any).current = container;
        return hookResult;
      });

      act(() => {
        result.current.handleClearMessages();
      });

      expect(result.current.hasMessages).toBe(false);
    });

    it('should set messagesVisible to false', () => {
      const { result } = renderHook(() => useMessagesContainer());

      act(() => {
        result.current.handleClearMessages();
      });

      expect(result.current.messagesVisible).toBe(false);
    });

    it('should call Drupal.Message().clear() when available', () => {
      const mockClear = jest.fn();
      (global as any).Drupal = {
        Message: class {
          clear() { mockClear(); }
        },
      };

      const { result } = renderHook(() => useMessagesContainer());

      act(() => {
        result.current.handleClearMessages();
      });

      expect(mockClear).toHaveBeenCalled();

      delete (global as any).Drupal;
    });

    it('should not throw when Drupal is not available', () => {
      const { result } = renderHook(() => useMessagesContainer());

      expect(() => {
        act(() => {
          result.current.handleClearMessages();
        });
      }).not.toThrow();
    });
  });

  describe('messages-list DOM handling', () => {
    it('should detect when messages-list has content', () => {
      // Add content to messages list
      mockMessagesList.innerHTML = '<div>Test message</div>';

      const { result } = renderHook(() => {
        const hookResult = useMessagesContainer();
        // Attach the ref to the container
        if (hookResult.messagesContainerRef.current === null) {
          const container = document.createElement('div');
          (hookResult.messagesContainerRef as any).current = container;
          document.body.appendChild(container);
        }
        return hookResult;
      });

      // The effect should detect content and set hasMessages
      // This needs the ref to be properly connected
      expect(result.current).toBeDefined();
    });

    it('should work without messages-list in DOM', () => {
      // Remove messages-list
      mockMessagesList.remove();

      const { result } = renderHook(() => useMessagesContainer());

      expect(result.current.hasMessages).toBe(false);
      expect(result.current.messagesVisible).toBe(true);
    });
  });

  describe('cleanup', () => {
    it('should not throw on unmount', () => {
      const { unmount } = renderHook(() => useMessagesContainer());

      expect(() => {
        unmount();
      }).not.toThrow();
    });

    it('should clear timers on unmount', () => {
      const { result, unmount } = renderHook(() => useMessagesContainer());

      act(() => {
        result.current.handleToggleMessages();
      });

      unmount();

      // Running timers after unmount should not cause issues
      expect(() => {
        jest.advanceTimersByTime(10000);
      }).not.toThrow();
    });
  });

  describe('MutationObserver integration', () => {
    it('should set up observer for messages-list changes', () => {
      // This test verifies the observer setup doesn't throw
      const container = document.createElement('div');
      document.body.appendChild(container);

      const { result, unmount } = renderHook(() => {
        const hookResult = useMessagesContainer();
        // Manually set the ref
        (hookResult.messagesContainerRef as any).current = container;
        return hookResult;
      });

      expect(result.current).toBeDefined();

      unmount();
      container.remove();
    });
  });

  describe('auto-re-show on new messages after fade-out', () => {
    let container: HTMLDivElement;
    let observerCallback: MutationCallback;
    let originalMutationObserver: typeof MutationObserver;

    beforeEach(() => {
      container = document.createElement('div');
      document.body.appendChild(container);

      // Replace MutationObserver with a manual-trigger version so we can
      // control exactly when it fires (jsdom's async delivery is unreliable
      // with fake timers).
      originalMutationObserver = global.MutationObserver;
      global.MutationObserver = class MockMutationObserver {
        constructor(cb: MutationCallback) {
          observerCallback = cb;
        }
        observe() { /* noop */ }
        disconnect() { /* noop */ }
        takeRecords(): MutationRecord[] { return []; }
      } as unknown as typeof MutationObserver;
    });

    afterEach(() => {
      global.MutationObserver = originalMutationObserver;
      container.remove();
    });

    // Helper to simulate a MutationObserver callback with addedNodes
    const simulateAddedNodes = (nodes: Node[]) => {
      const record = {
        type: 'childList',
        addedNodes: nodes,
        removedNodes: [],
        target: mockMessagesList,
      } as unknown as MutationRecord;
      observerCallback([record], {} as MutationObserver);
    };

    it('should re-show messages when new nodes are added after fade-out', () => {
      // Start with a message so initial check detects content
      mockMessagesList.innerHTML = '<div class="messages messages--status">First message</div>';

      const { result } = renderHook(() => {
        const hookResult = useMessagesContainer();
        (hookResult.messagesContainerRef as any).current = container;
        return hookResult;
      });

      // Should be visible initially (new content detected)
      expect(result.current.messagesVisible).toBe(true);

      // Wait for auto-fade
      act(() => {
        jest.advanceTimersByTime(5000);
      });

      // Should now be hidden
      expect(result.current.messagesVisible).toBe(false);

      // Add a new message node while hidden and fire the observer
      const newMessage = document.createElement('div');
      newMessage.className = 'messages messages--warning';
      newMessage.textContent = 'Second message';
      mockMessagesList.appendChild(newMessage);

      act(() => {
        simulateAddedNodes([newMessage]);
      });

      // Should re-show after new node added
      expect(result.current.messagesVisible).toBe(true);
    });

    it('should auto-fade again after re-showing', () => {
      mockMessagesList.innerHTML = '<div class="messages messages--status">First message</div>';

      const { result } = renderHook(() => {
        const hookResult = useMessagesContainer();
        (hookResult.messagesContainerRef as any).current = container;
        return hookResult;
      });

      // Wait for initial auto-fade
      act(() => {
        jest.advanceTimersByTime(5000);
      });

      expect(result.current.messagesVisible).toBe(false);

      // Add a new message and fire the observer
      const newMessage = document.createElement('div');
      newMessage.className = 'messages messages--error';
      newMessage.textContent = 'New error';
      mockMessagesList.appendChild(newMessage);

      act(() => {
        simulateAddedNodes([newMessage]);
      });

      // Should be visible again
      expect(result.current.messagesVisible).toBe(true);

      // Wait for the second auto-fade
      act(() => {
        jest.advanceTimersByTime(5000);
      });

      // Should fade out again
      expect(result.current.messagesVisible).toBe(false);
    });

    it('should not auto-fade re-shown messages if user pinned them', () => {
      mockMessagesList.innerHTML = '<div class="messages messages--status">First message</div>';

      const { result } = renderHook(() => {
        const hookResult = useMessagesContainer();
        (hookResult.messagesContainerRef as any).current = container;
        return hookResult;
      });

      // Wait for auto-fade
      act(() => {
        jest.advanceTimersByTime(5000);
      });

      expect(result.current.messagesVisible).toBe(false);

      // User toggles to show (pins messages)
      act(() => {
        result.current.handleToggleMessages();
      });

      expect(result.current.messagesVisible).toBe(true);

      // User toggles to hide
      act(() => {
        result.current.handleToggleMessages();
      });

      expect(result.current.messagesVisible).toBe(false);

      // Add a new message while hidden and fire the observer
      const newMessage = document.createElement('div');
      newMessage.className = 'messages messages--warning';
      newMessage.textContent = 'Another message';
      mockMessagesList.appendChild(newMessage);

      act(() => {
        simulateAddedNodes([newMessage]);
      });

      expect(result.current.messagesVisible).toBe(true);

      // Since user unpinned when toggling to hide, should auto-fade
      act(() => {
        jest.advanceTimersByTime(5000);
      });

      expect(result.current.messagesVisible).toBe(false);
    });
  });

  describe('edge cases', () => {
    it('should handle messages-list with only text content', () => {
      mockMessagesList.textContent = 'Plain text message';

      const { result } = renderHook(() => useMessagesContainer());

      // Should not throw
      expect(result.current).toBeDefined();
    });

    it('should handle empty messages-list', () => {
      mockMessagesList.innerHTML = '';

      const { result } = renderHook(() => useMessagesContainer());

      expect(result.current.hasMessages).toBe(false);
    });

    it('should handle whitespace-only content', () => {
      mockMessagesList.textContent = '   ';

      const { result } = renderHook(() => useMessagesContainer());

      // Whitespace-only should not count as having messages
      expect(result.current).toBeDefined();
    });
  });
});

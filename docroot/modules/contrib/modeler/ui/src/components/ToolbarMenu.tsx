/**
 * ToolbarMenu - Kebab menu for secondary toolbar actions
 *
 * Contains settings, export, and dark/light mode toggle that were
 * moved out of the main toolbar to reduce clutter.
 */

import React, { useCallback, useRef, useState } from 'react';
import { FiMoreVertical, FiSettings, FiDownload, FiMoon, FiSun, FiBookOpen } from 'react-icons/fi';
import { useClickOutside } from '../hooks/useClickOutside';
import { useUISettingsStore } from '../store/useUISettingsStore';
import { t } from '../utils/translation';

interface ToolbarMenuItem {
  id: string;
  icon: React.ReactNode;
  label: string;
  onClick: () => void;
  disabled?: boolean;
  active?: boolean;
}

interface ToolbarMenuProps {
  onOpenMetadata: () => void;
  onExport?: () => void;
  canExport?: boolean;
}

const ToolbarMenu: React.FC<ToolbarMenuProps> = ({
  onOpenMetadata,
  onExport,
  canExport = false,
}) => {
  const [isOpen, setIsOpen] = useState(false);
  const containerRef = useRef<HTMLDivElement>(null);
  const buttonRef = useRef<HTMLButtonElement>(null);

  const darkMode = useUISettingsStore(state => state.darkMode);
  const toggleDarkMode = useUISettingsStore(state => state.toggleDarkMode);

  useClickOutside(
    isOpen,
    [containerRef],
    useCallback(() => setIsOpen(false), []),
  );

  const handleToggle = (e: React.MouseEvent<HTMLButtonElement>) => {
    e.preventDefault();
    e.stopPropagation();
    setIsOpen(prev => !prev);
  };

  const handleItemClick = (item: ToolbarMenuItem) => {
    if (item.disabled) return;
    item.onClick();
    setIsOpen(false);
  };

  const handleKeyDown = (e: React.KeyboardEvent, item: ToolbarMenuItem) => {
    if (e.key === 'Enter' || e.key === ' ') {
      e.preventDefault();
      handleItemClick(item);
    }
    if (e.key === 'Escape') {
      setIsOpen(false);
      buttonRef.current?.focus();
    }
  };

  const items: ToolbarMenuItem[] = [
    {
      id: 'settings',
      icon: <FiSettings />,
      label: t('Model Settings'),
      onClick: onOpenMetadata,
    },
    ...(canExport && onExport ? [{
      id: 'export',
      icon: <FiDownload />,
      label: t('Export Model'),
      onClick: onExport,
    }] : []),
    {
      id: 'dark-mode',
      icon: darkMode ? <FiSun /> : <FiMoon />,
      label: darkMode ? t('Switch to Light Mode') : t('Switch to Dark Mode'),
      onClick: toggleDarkMode,
      active: darkMode,
    },
    {
      id: 'documentation',
      icon: <FiBookOpen />,
      label: t('Documentation'),
      onClick: () => window.open('https://project.pages.drupalcode.org/modeler/', '_blank', 'noopener,noreferrer'),
    },
  ];

  return (
    <div className="toolbar-menu" ref={containerRef}>
      <button
        ref={buttonRef}
        type="button"
        className="toolbar-btn toolbar-menu-trigger"
        onClick={handleToggle}
        aria-haspopup="menu"
        aria-expanded={isOpen}
        aria-label={t('More options')}
        title={t('More options')}
      >
        <FiMoreVertical />
      </button>

      {isOpen && (
        <ul
          className="toolbar-menu-dropdown"
          role="menu"
          aria-label={t('Additional options')}
        >
          {items.map(item => (
            <li
              key={item.id}
              role="menuitem"
              className={`toolbar-menu-item${item.active ? ' active' : ''}${item.disabled ? ' disabled' : ''}`}
              onClick={() => handleItemClick(item)}
              onKeyDown={(e) => handleKeyDown(e, item)}
              tabIndex={item.disabled ? -1 : 0}
              aria-disabled={item.disabled}
            >
              <span className="toolbar-menu-item-icon">{item.icon}</span>
              <span className="toolbar-menu-item-label">{item.label}</span>
            </li>
          ))}
        </ul>
      )}
    </div>
  );
};

export default ToolbarMenu;

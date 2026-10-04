/**
 * InfoPopup - A small popup that displays metadata information when
 * the user clicks an "i" (information) icon in a panel header.
 *
 * Renders a list of label/value pairs in a positioned popup with
 * click-outside-to-close behavior.
 */
import React, { useEffect, useRef, useCallback } from 'react';
import { t } from '../utils/translation';

export interface InfoItem {
  label: string;
  value: string | React.ReactNode;
  show?: boolean;
  isError?: boolean;
}

interface InfoPopupProps {
  items: InfoItem[];
  onClose: () => void;
}

const InfoPopup: React.FC<InfoPopupProps> = ({ items, onClose }) => {
  const popupRef = useRef<HTMLDivElement>(null);

  const handleClickOutside = useCallback((e: MouseEvent) => {
    if (popupRef.current && !popupRef.current.contains(e.target as HTMLElement)) {
      onClose();
    }
  }, [onClose]);

  const handleKeyDown = useCallback((e: KeyboardEvent) => {
    if (e.key === 'Escape') {
      onClose();
    }
  }, [onClose]);

  useEffect(() => {
    document.addEventListener('mousedown', handleClickOutside);
    document.addEventListener('keydown', handleKeyDown);
    return () => {
      document.removeEventListener('mousedown', handleClickOutside);
      document.removeEventListener('keydown', handleKeyDown);
    };
  }, [handleClickOutside, handleKeyDown]);

  const visibleItems = items.filter(item => item.show !== false);

  if (visibleItems.length === 0) {
    return null;
  }

  return (
    <div
      className="info-popup"
      ref={popupRef}
      role="dialog"
      aria-modal="true"
      aria-label={t('Metadata')}
    >
      <div className="info-popup-content">
        {visibleItems.map((item, index) => (
          <div key={index} className="info-popup-item">
            <span className="info-popup-label">{item.label}</span>
            <span className={`info-popup-value${item.isError ? ' error' : ''}`}>{item.value}</span>
          </div>
        ))}
      </div>
    </div>
  );
};

export default InfoPopup;

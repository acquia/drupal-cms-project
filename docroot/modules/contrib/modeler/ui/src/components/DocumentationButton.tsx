import React, { useState } from 'react';
import { FiBookOpen } from 'react-icons/fi';
import DocumentationPopup from './DocumentationPopup';
import { t } from '../utils/translation';

interface DocumentationButtonProps {
  url: string | null | undefined;
  title: string;
  className?: string;
  size?: number;
}

const DocumentationButton: React.FC<DocumentationButtonProps> = ({
  url,
  title,
  className = '',
  size = 14,
}) => {
  const [isPopupOpen, setIsPopupOpen] = useState(false);

  // Don't render if no URL provided
  if (!url) return null;

  const handleClick = (e: React.MouseEvent) => {
    e.preventDefault();
    e.stopPropagation();
    setIsPopupOpen(true);
  };

  const handleKeyDown = (e: React.KeyboardEvent) => {
    if (e.key === 'Enter' || e.key === ' ') {
      e.preventDefault();
      e.stopPropagation();
      setIsPopupOpen(true);
    }
  };

  return (
    <>
      <span
        role="button"
        tabIndex={0}
        className={`documentation-btn ${className}`}
        onClick={handleClick}
        onKeyDown={handleKeyDown}
        title={t('View documentation for @title', { '@title': title })}
      >
        <FiBookOpen size={size} />
      </span>
      <DocumentationPopup
        url={url}
        title={title}
        isOpen={isPopupOpen}
        onClose={() => setIsPopupOpen(false)}
      />
    </>
  );
};

export default DocumentationButton;

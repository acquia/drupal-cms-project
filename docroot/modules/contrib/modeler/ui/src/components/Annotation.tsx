import React, { memo } from 'react';
import { FiMessageSquare } from 'react-icons/fi';
import { t } from '../utils/translation';
import { UI_DIMENSIONS } from '../constants/dimensions';

interface AnnotationProps {
  annotation: string;
  isVisible?: boolean;
  onToggle?: () => void;
  offset?: boolean;
}

const Annotation = memo<AnnotationProps>(({ annotation, isVisible = false, onToggle, offset = false }) => {
  const handleClick = (e: React.MouseEvent) => {
    e.stopPropagation();
    if (onToggle) {
      onToggle();
    }
  };

  return (
    <>
      <button
        className={`annotation-icon ${isVisible ? 'active' : ''}`}
        title={annotation}
        aria-label={t('Toggle annotation')}
        onClick={handleClick}
      >
        <FiMessageSquare size={UI_DIMENSIONS.ICON_SIZE_SMALL} />
      </button>
      {isVisible && (
        <div className={`annotation-label ${offset ? 'annotation-label-offset' : ''}`}>
          {annotation}
        </div>
      )}
    </>
  );
});

Annotation.displayName = t('Annotation');

export default Annotation;

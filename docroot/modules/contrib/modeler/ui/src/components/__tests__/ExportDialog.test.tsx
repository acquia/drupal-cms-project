import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react';
import ExportDialog from '../ExportDialog';
import type { ExportFormat } from '../../hooks/useExport';

describe('ExportDialog', () => {
  const allFormats: ExportFormat[] = ['recipe', 'archive', 'json', 'svg'];

  const defaultProps = {
    isOpen: true,
    onClose: jest.fn(),
    availableFormats: allFormats,
    hasReplayData: false,
    requiredModules: [] as string[],
    onExport: jest.fn(),
    isExporting: false,
  };

  beforeEach(() => {
    jest.clearAllMocks();
  });

  // -------------------------------------------------------------------------
  // rendering
  // -------------------------------------------------------------------------
  describe('rendering', () => {
    it('should not render when isOpen is false', () => {
      render(<ExportDialog {...defaultProps} isOpen={false} />);
      expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    });

    it('should render when isOpen is true', () => {
      render(<ExportDialog {...defaultProps} />);
      expect(screen.getByRole('dialog')).toBeInTheDocument();
    });

    it('should display the dialog title', () => {
      render(<ExportDialog {...defaultProps} />);
      expect(screen.getByRole('heading', { level: 2 })).toHaveTextContent('Export Model');
    });

    it('should display instruction text', () => {
      render(<ExportDialog {...defaultProps} />);
      expect(screen.getByText('Select an export format:')).toBeInTheDocument();
    });

    it('should render all available format options', () => {
      render(<ExportDialog {...defaultProps} />);
      expect(screen.getByText('Recipe')).toBeInTheDocument();
      expect(screen.getByText('Archive')).toBeInTheDocument();
      expect(screen.getByText('JSON')).toBeInTheDocument();
      expect(screen.getByText('SVG')).toBeInTheDocument();
    });

    it('should render only the formats in availableFormats', () => {
      render(
        <ExportDialog {...defaultProps} availableFormats={['json', 'svg']} />,
      );
      expect(screen.queryByText('Recipe')).not.toBeInTheDocument();
      expect(screen.queryByText('Archive')).not.toBeInTheDocument();
      expect(screen.getByText('JSON')).toBeInTheDocument();
      expect(screen.getByText('SVG')).toBeInTheDocument();
    });

    it('should render Export and Cancel buttons', () => {
      render(<ExportDialog {...defaultProps} />);
      expect(screen.getByRole('button', { name: /^Export$/i })).toBeInTheDocument();
      expect(screen.getByRole('button', { name: /Cancel/i })).toBeInTheDocument();
    });

    it('should display format descriptions', () => {
      render(<ExportDialog {...defaultProps} />);
      expect(screen.getByText(/Export as a Drupal recipe/)).toBeInTheDocument();
      expect(screen.getByText(/Export as a \.tar\.gz archive/)).toBeInTheDocument();
      expect(screen.getByText(/Export the model data as a JSON file/)).toBeInTheDocument();
      expect(screen.getByText(/Export the visual canvas as an SVG image/)).toBeInTheDocument();
    });
  });

  // -------------------------------------------------------------------------
  // format selection
  // -------------------------------------------------------------------------
  describe('format selection', () => {
    it('should have no format selected initially', () => {
      render(<ExportDialog {...defaultProps} />);
      const radios = screen.getAllByRole('radio');
      radios.forEach((radio) => {
        expect(radio).toHaveAttribute('aria-checked', 'false');
      });
    });

    it('should select a format when clicked', () => {
      render(<ExportDialog {...defaultProps} />);
      const jsonOption = screen.getByText('JSON').closest('button')!;
      fireEvent.click(jsonOption);
      expect(jsonOption).toHaveAttribute('aria-checked', 'true');
    });

    it('should deselect previous format when a new one is selected', () => {
      render(<ExportDialog {...defaultProps} />);
      const jsonOption = screen.getByText('JSON').closest('button')!;
      const svgOption = screen.getByText('SVG').closest('button')!;

      fireEvent.click(jsonOption);
      expect(jsonOption).toHaveAttribute('aria-checked', 'true');

      fireEvent.click(svgOption);
      expect(jsonOption).toHaveAttribute('aria-checked', 'false');
      expect(svgOption).toHaveAttribute('aria-checked', 'true');
    });
  });

  // -------------------------------------------------------------------------
  // JSON-specific options
  // -------------------------------------------------------------------------
  describe('JSON-specific options', () => {
    it('should not show replay checkbox when format is not json', () => {
      render(<ExportDialog {...defaultProps} hasReplayData={true} />);
      expect(screen.queryByText('Include replay data')).not.toBeInTheDocument();
    });

    it('should not show replay checkbox when json is selected but no replay data', () => {
      render(<ExportDialog {...defaultProps} hasReplayData={false} />);
      const jsonOption = screen.getByText('JSON').closest('button')!;
      fireEvent.click(jsonOption);
      expect(screen.queryByText('Include replay data')).not.toBeInTheDocument();
    });

    it('should show replay checkbox when json is selected and replay data exists', () => {
      render(<ExportDialog {...defaultProps} hasReplayData={true} />);
      const jsonOption = screen.getByText('JSON').closest('button')!;
      fireEvent.click(jsonOption);
      expect(screen.getByText('Include replay data')).toBeInTheDocument();
    });

    it('should show required modules when json is selected and modules exist', () => {
      render(
        <ExportDialog
          {...defaultProps}
          requiredModules={['workflow_base', 'workflow_content']}
        />,
      );
      const jsonOption = screen.getByText('JSON').closest('button')!;
      fireEvent.click(jsonOption);
      expect(screen.getByText('Required modules:')).toBeInTheDocument();
      expect(screen.getByText('workflow_base, workflow_content')).toBeInTheDocument();
    });

    it('should not show required modules section when list is empty', () => {
      render(<ExportDialog {...defaultProps} requiredModules={[]} />);
      const jsonOption = screen.getByText('JSON').closest('button')!;
      fireEvent.click(jsonOption);
      expect(screen.queryByText('Required modules:')).not.toBeInTheDocument();
    });

    it('should hide JSON options when switching to another format', () => {
      render(
        <ExportDialog
          {...defaultProps}
          hasReplayData={true}
          requiredModules={['workflow_base']}
        />,
      );
      const jsonOption = screen.getByText('JSON').closest('button')!;
      fireEvent.click(jsonOption);
      expect(screen.getByText('Include replay data')).toBeInTheDocument();

      const svgOption = screen.getByText('SVG').closest('button')!;
      fireEvent.click(svgOption);
      expect(screen.queryByText('Include replay data')).not.toBeInTheDocument();
    });
  });

  // -------------------------------------------------------------------------
  // button states
  // -------------------------------------------------------------------------
  describe('button states', () => {
    it('should disable Export button when no format is selected', () => {
      render(<ExportDialog {...defaultProps} />);
      expect(screen.getByRole('button', { name: /^Export$/i })).toBeDisabled();
    });

    it('should enable Export button when a format is selected', () => {
      render(<ExportDialog {...defaultProps} />);
      fireEvent.click(screen.getByText('SVG').closest('button')!);
      expect(screen.getByRole('button', { name: /^Export$/i })).toBeEnabled();
    });

    it('should disable Export button when isExporting is true', () => {
      render(<ExportDialog {...defaultProps} isExporting={true} />);
      fireEvent.click(screen.getByText('SVG').closest('button')!);
      expect(screen.getByRole('button', { name: /Exporting/i })).toBeDisabled();
    });

    it('should show "Exporting..." text when isExporting is true', () => {
      render(<ExportDialog {...defaultProps} isExporting={true} />);
      expect(screen.getByText('Exporting...')).toBeInTheDocument();
    });

    it('should disable Cancel button when isExporting is true', () => {
      render(<ExportDialog {...defaultProps} isExporting={true} />);
      expect(screen.getByRole('button', { name: /Cancel/i })).toBeDisabled();
    });
  });

  // -------------------------------------------------------------------------
  // interactions
  // -------------------------------------------------------------------------
  describe('interactions', () => {
    it('should call onExport with selected format when Export is clicked', () => {
      const onExport = jest.fn();
      render(<ExportDialog {...defaultProps} onExport={onExport} />);

      fireEvent.click(screen.getByText('SVG').closest('button')!);
      fireEvent.click(screen.getByRole('button', { name: /^Export$/i }));

      expect(onExport).toHaveBeenCalledWith('svg', undefined);
    });

    it('should call onExport with includeReplayData=false for json by default', () => {
      const onExport = jest.fn();
      render(
        <ExportDialog {...defaultProps} onExport={onExport} hasReplayData={true} />,
      );

      fireEvent.click(screen.getByText('JSON').closest('button')!);
      fireEvent.click(screen.getByRole('button', { name: /^Export$/i }));

      expect(onExport).toHaveBeenCalledWith('json', false);
    });

    it('should call onExport with includeReplayData=true when checkbox is checked', () => {
      const onExport = jest.fn();
      render(
        <ExportDialog {...defaultProps} onExport={onExport} hasReplayData={true} />,
      );

      fireEvent.click(screen.getByText('JSON').closest('button')!);
      fireEvent.click(screen.getByRole('checkbox'));
      fireEvent.click(screen.getByRole('button', { name: /^Export$/i }));

      expect(onExport).toHaveBeenCalledWith('json', true);
    });

    it('should not call onExport when no format is selected', () => {
      const onExport = jest.fn();
      render(<ExportDialog {...defaultProps} onExport={onExport} />);

      // Try to click Export without selecting a format
      fireEvent.click(screen.getByRole('button', { name: /^Export$/i }));

      expect(onExport).not.toHaveBeenCalled();
    });

    it('should call onClose when Cancel button is clicked', () => {
      const onClose = jest.fn();
      render(<ExportDialog {...defaultProps} onClose={onClose} />);

      fireEvent.click(screen.getByRole('button', { name: /Cancel/i }));

      expect(onClose).toHaveBeenCalledTimes(1);
    });

    it('should call onClose when clicking on the overlay', () => {
      const onClose = jest.fn();
      render(<ExportDialog {...defaultProps} onClose={onClose} />);

      const overlay = document.querySelector('.export-dialog-overlay');
      expect(overlay).toBeInTheDocument();
      fireEvent.click(overlay!);

      expect(onClose).toHaveBeenCalledTimes(1);
    });

    it('should not call onClose when clicking inside the dialog', () => {
      const onClose = jest.fn();
      render(<ExportDialog {...defaultProps} onClose={onClose} />);

      const dialog = document.querySelector('.export-dialog');
      expect(dialog).toBeInTheDocument();
      fireEvent.click(dialog!);

      expect(onClose).not.toHaveBeenCalled();
    });
  });

  // -------------------------------------------------------------------------
  // accessibility
  // -------------------------------------------------------------------------
  describe('accessibility', () => {
    it('should have role="dialog" with aria-modal="true"', () => {
      render(<ExportDialog {...defaultProps} />);
      const dialog = screen.getByRole('dialog');
      expect(dialog).toHaveAttribute('aria-modal', 'true');
    });

    it('should have aria-labelledby pointing to the title', () => {
      render(<ExportDialog {...defaultProps} />);
      const dialog = screen.getByRole('dialog');
      expect(dialog).toHaveAttribute('aria-labelledby', 'export-dialog-title');

      const title = document.getElementById('export-dialog-title');
      expect(title).toBeInTheDocument();
      expect(title).toHaveTextContent('Export Model');
    });

    it('should have a radiogroup with aria-label', () => {
      render(<ExportDialog {...defaultProps} />);
      const radiogroup = screen.getByRole('radiogroup');
      expect(radiogroup).toHaveAttribute('aria-label', 'Export format');
    });

    it('should have radio buttons with aria-checked', () => {
      render(<ExportDialog {...defaultProps} />);
      const radios = screen.getAllByRole('radio');
      expect(radios).toHaveLength(4);
      radios.forEach((radio) => {
        expect(radio).toHaveAttribute('aria-checked');
      });
    });

    it('should have proper button types', () => {
      render(<ExportDialog {...defaultProps} />);
      const buttons = screen.getAllByRole('button');
      buttons.forEach((button) => {
        expect(button).toHaveAttribute('type', 'button');
      });
    });
  });

  // -------------------------------------------------------------------------
  // styling
  // -------------------------------------------------------------------------
  describe('styling', () => {
    it('should have correct CSS classes', () => {
      render(<ExportDialog {...defaultProps} />);
      expect(document.querySelector('.export-dialog-overlay')).toBeInTheDocument();
      expect(document.querySelector('.export-dialog')).toBeInTheDocument();
      expect(document.querySelector('.export-dialog-header')).toBeInTheDocument();
      expect(document.querySelector('.export-dialog-body')).toBeInTheDocument();
      expect(document.querySelector('.export-dialog-footer')).toBeInTheDocument();
    });

    it('should have correct button classes', () => {
      render(<ExportDialog {...defaultProps} />);
      const exportBtn = screen.getByRole('button', { name: /^Export$/i });
      const cancelBtn = screen.getByRole('button', { name: /Cancel/i });

      expect(exportBtn).toHaveClass('btn', 'btn-primary');
      expect(cancelBtn).toHaveClass('btn', 'btn-secondary');
    });

    it('should add "selected" class to selected format', () => {
      render(<ExportDialog {...defaultProps} />);
      const svgOption = screen.getByText('SVG').closest('button')!;
      fireEvent.click(svgOption);
      expect(svgOption).toHaveClass('selected');
    });

    it('should have format option structure', () => {
      render(<ExportDialog {...defaultProps} />);
      expect(document.querySelector('.export-format-list')).toBeInTheDocument();
      expect(document.querySelector('.export-format-option')).toBeInTheDocument();
      expect(document.querySelector('.export-format-icon')).toBeInTheDocument();
      expect(document.querySelector('.export-format-label')).toBeInTheDocument();
      expect(document.querySelector('.export-format-description')).toBeInTheDocument();
    });
  });
});

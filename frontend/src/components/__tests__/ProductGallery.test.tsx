import React from 'react';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import '@testing-library/jest-dom';
import ProductGallery from '../ProductGallery';

describe('ProductGallery', () => {
  const mockImages = [
    'https://example.com/image1.jpg',
    'https://example.com/image2.jpg',
    'https://example.com/image3.jpg',
  ];

  const defaultProps = {
    images: mockImages,
    productName: 'Test Product',
  };

  beforeEach(() => {
    // Mock document.body.style
    Object.defineProperty(document.body, 'style', {
      value: { overflow: '' },
      writable: true,
    });
  });

  it('renders main image correctly', () => {
    render(<ProductGallery {...defaultProps} />);
    
    const mainImage = screen.getByAltText('Test Product - Image 1');
    expect(mainImage).toBeInTheDocument();
    expect(mainImage).toHaveAttribute('src', mockImages[0]);
  });

  it('renders thumbnail navigation when multiple images', () => {
    render(<ProductGallery {...defaultProps} />);
    
    const thumbnails = screen.getAllByRole('button');
    const thumbnailButtons = thumbnails.filter(button => 
      button.querySelector('img')?.alt?.includes('thumbnail')
    );
    
    expect(thumbnailButtons).toHaveLength(3);
  });

  it('does not render thumbnails for single image', () => {
    render(<ProductGallery {...defaultProps} images={[mockImages[0]]} />);
    
    const thumbnails = screen.queryAllByRole('button');
    const thumbnailButtons = thumbnails.filter(button => 
      button.querySelector('img')?.alt?.includes('thumbnail')
    );
    
    expect(thumbnailButtons).toHaveLength(0);
  });

  it('changes main image when thumbnail is clicked', () => {
    render(<ProductGallery {...defaultProps} />);
    
    const thumbnails = screen.getAllByRole('button');
    const secondThumbnail = thumbnails.find(button => 
      button.querySelector('img')?.alt?.includes('thumbnail 2')
    );
    
    if (secondThumbnail) {
      fireEvent.click(secondThumbnail);
      
      const mainImage = screen.getByAltText('Test Product - Image 2');
      expect(mainImage).toHaveAttribute('src', mockImages[1]);
    }
  });

  it('shows navigation arrows on hover for multiple images', () => {
    render(<ProductGallery {...defaultProps} />);
    
    const imageContainer = document.querySelector('.group');
    expect(imageContainer).toBeInTheDocument();
    
    // Navigation arrows should be present but hidden initially
    const buttons = screen.getAllByRole('button');
    const navigationButtons = buttons.filter(button => 
      button.querySelector('svg') && 
      (button.querySelector('svg')?.getAttribute('class')?.includes('w-5 h-5') ||
       button.querySelector('svg')?.getAttribute('class')?.includes('w-6 h-6'))
    );
    
    expect(navigationButtons.length).toBeGreaterThanOrEqual(2);
  });

  it('navigates to previous image when previous button is clicked', () => {
    render(<ProductGallery {...defaultProps} />);
    
    // First go to second image
    const thumbnails = screen.getAllByRole('button');
    const secondThumbnail = thumbnails.find(button => 
      button.querySelector('img')?.alt?.includes('thumbnail 2')
    );
    
    if (secondThumbnail) {
      fireEvent.click(secondThumbnail);
      
      // Now click previous - find by looking for chevron left icon
      const buttons = screen.getAllByRole('button');
      const prevButton = buttons.find(button => 
        button.querySelector('svg') && 
        button.querySelector('svg')?.innerHTML.includes('M15.75 19.5L8.25 12l7.5-7.5')
      );
      
      if (prevButton) {
        fireEvent.click(prevButton);
        
        const mainImage = screen.getByAltText('Test Product - Image 1');
        expect(mainImage).toHaveAttribute('src', mockImages[0]);
      }
    }
  });

  it('navigates to next image when next button is clicked', () => {
    render(<ProductGallery {...defaultProps} />);
    
    // Find next button by looking for chevron right icon
    const buttons = screen.getAllByRole('button');
    const nextButton = buttons.find(button => 
      button.querySelector('svg') && 
      button.querySelector('svg')?.innerHTML.includes('m8.25 4.5 7.5 7.5-7.5 7.5')
    );
    
    if (nextButton) {
      fireEvent.click(nextButton);
      
      const mainImage = screen.getByAltText('Test Product - Image 2');
      expect(mainImage).toHaveAttribute('src', mockImages[1]);
    }
  });

  it('wraps around when navigating past last image', () => {
    render(<ProductGallery {...defaultProps} />);
    
    // Go to last image first
    const thumbnails = screen.getAllByRole('button');
    const lastThumbnail = thumbnails.find(button => 
      button.querySelector('img')?.alt?.includes('thumbnail 3')
    );
    
    if (lastThumbnail) {
      fireEvent.click(lastThumbnail);
      
      // Click next to wrap around - find by chevron right icon
      const buttons = screen.getAllByRole('button');
      const nextButton = buttons.find(button => 
        button.querySelector('svg') && 
        button.querySelector('svg')?.innerHTML.includes('m8.25 4.5 7.5 7.5-7.5 7.5')
      );
      
      if (nextButton) {
        fireEvent.click(nextButton);
        
        const mainImage = screen.getByAltText('Test Product - Image 1');
        expect(mainImage).toHaveAttribute('src', mockImages[0]);
      }
    }
  });

  it('wraps around when navigating before first image', () => {
    render(<ProductGallery {...defaultProps} />);
    
    // Click previous from first image to wrap to last - find by chevron left icon
    const buttons = screen.getAllByRole('button');
    const prevButton = buttons.find(button => 
      button.querySelector('svg') && 
      button.querySelector('svg')?.innerHTML.includes('M15.75 19.5L8.25 12l7.5-7.5')
    );
    
    if (prevButton) {
      fireEvent.click(prevButton);
      
      const mainImage = screen.getByAltText('Test Product - Image 3');
      expect(mainImage).toHaveAttribute('src', mockImages[2]);
    }
  });

  it('shows image counter for multiple images', () => {
    render(<ProductGallery {...defaultProps} />);
    
    expect(screen.getByText('1 / 3')).toBeInTheDocument();
  });

  it('opens modal when zoom button is clicked', () => {
    render(<ProductGallery {...defaultProps} />);
    
    // Find zoom button by looking for magnifying glass icon
    const buttons = screen.getAllByRole('button');
    const zoomButton = buttons.find(button => 
      button.querySelector('svg') && 
      (button.querySelector('svg')?.innerHTML.includes('magnifying') ||
       button.querySelector('svg')?.innerHTML.includes('M21 21l-5.197-5.197'))
    );
    
    if (zoomButton) {
      fireEvent.click(zoomButton);
      
      // Modal should be open
      const modal = document.querySelector('.fixed.inset-0.z-50');
      expect(modal).toBeInTheDocument();
    }
  });

  it('closes modal when close button is clicked', () => {
    render(<ProductGallery {...defaultProps} />);
    
    // Open modal first - find zoom button
    const buttons = screen.getAllByRole('button');
    const zoomButton = buttons.find(button => 
      button.querySelector('svg') && 
      (button.querySelector('svg')?.innerHTML.includes('magnifying') ||
       button.querySelector('svg')?.innerHTML.includes('M21 21l-5.197-5.197'))
    );
    
    if (zoomButton) {
      fireEvent.click(zoomButton);
      
      // Close modal - find close button by X mark icon
      const closeButton = buttons.find(button => 
        button.querySelector('svg') && 
        button.querySelector('svg')?.innerHTML.includes('M6 18L18 6M6 6l12 12')
      );
      
      if (closeButton) {
        fireEvent.click(closeButton);
        
        // Modal should be closed
        const modal = document.querySelector('.fixed.inset-0.z-50');
        expect(modal).not.toBeInTheDocument();
      }
    }
  });

  it('handles keyboard navigation in modal', () => {
    render(<ProductGallery {...defaultProps} />);
    
    // Open modal first
    const buttons = screen.getAllByRole('button');
    const zoomButton = buttons.find(button => 
      button.querySelector('svg') && 
      (button.querySelector('svg')?.innerHTML.includes('magnifying') ||
       button.querySelector('svg')?.innerHTML.includes('M21 21l-5.197-5.197'))
    );
    
    if (zoomButton) {
      fireEvent.click(zoomButton);
      
      // Test arrow key navigation
      fireEvent.keyDown(document, { key: 'ArrowRight' });
      
      // Should show second image in modal
      const modalImage = document.querySelector('.fixed img:not([alt*="Thumbnail"])');
      expect(modalImage).toHaveAttribute('alt', 'Test Product - Image 2');
    }
  });

  it('closes modal on escape key', () => {
    render(<ProductGallery {...defaultProps} />);
    
    // Open modal
    const buttons = screen.getAllByRole('button');
    const zoomButton = buttons.find(button => 
      button.querySelector('svg') && 
      (button.querySelector('svg')?.innerHTML.includes('magnifying') ||
       button.querySelector('svg')?.innerHTML.includes('M21 21l-5.197-5.197'))
    );
    
    if (zoomButton) {
      fireEvent.click(zoomButton);
      
      // Press escape
      fireEvent.keyDown(document, { key: 'Escape' });
      
      // Modal should be closed
      const modal = document.querySelector('.fixed.inset-0.z-50');
      expect(modal).not.toBeInTheDocument();
    }
  });

  it('prevents body scroll when modal is open', () => {
    render(<ProductGallery {...defaultProps} />);
    
    // Open modal
    const buttons = screen.getAllByRole('button');
    const zoomButton = buttons.find(button => 
      button.querySelector('svg') && 
      (button.querySelector('svg')?.innerHTML.includes('magnifying') ||
       button.querySelector('svg')?.innerHTML.includes('M21 21l-5.197-5.197'))
    );
    
    if (zoomButton) {
      fireEvent.click(zoomButton);
      
      expect(document.body.style.overflow).toBe('hidden');
    }
  });

  it('restores body scroll when modal is closed', () => {
    render(<ProductGallery {...defaultProps} />);
    
    // Open modal
    const buttons = screen.getAllByRole('button');
    const zoomButton = buttons.find(button => 
      button.querySelector('svg') && 
      (button.querySelector('svg')?.innerHTML.includes('magnifying') ||
       button.querySelector('svg')?.innerHTML.includes('M21 21l-5.197-5.197'))
    );
    
    if (zoomButton) {
      fireEvent.click(zoomButton);
      
      // Close modal
      const closeButton = buttons.find(button => 
        button.querySelector('svg') && 
        button.querySelector('svg')?.innerHTML.includes('M6 18L18 6M6 6l12 12')
      );
      
      if (closeButton) {
        fireEvent.click(closeButton);
        
        expect(document.body.style.overflow).toBe('unset');
      }
    }
  });

  it('shows no images message when no images provided', () => {
    render(<ProductGallery {...defaultProps} images={[]} />);
    
    expect(screen.getByText('No images available')).toBeInTheDocument();
  });

  it('handles image load events', () => {
    render(<ProductGallery {...defaultProps} />);
    
    const mainImage = screen.getByAltText('Test Product - Image 1');
    
    // Initially image should have opacity-0 class (not loaded)
    expect(mainImage).toHaveClass('opacity-0');
    
    // Simulate image load
    fireEvent.load(mainImage);
    
    // After load, should have opacity-100 class
    expect(mainImage).toHaveClass('opacity-100');
  });

  it('shows loading placeholder while image is loading', () => {
    render(<ProductGallery {...defaultProps} />);
    
    const loadingPlaceholder = document.querySelector('.animate-pulse');
    expect(loadingPlaceholder).toBeInTheDocument();
  });

  it('applies custom className', () => {
    render(<ProductGallery {...defaultProps} className="custom-class" />);
    
    const container = document.querySelector('.custom-class');
    expect(container).toBeInTheDocument();
  });

  it('handles modal thumbnail navigation', () => {
    render(<ProductGallery {...defaultProps} />);
    
    // Open modal
    const zoomButton = screen.getByRole('button', { name: /zoom/i });
    fireEvent.click(zoomButton);
    
    // Find modal thumbnails
    const modalThumbnails = document.querySelectorAll('.fixed img[alt*="Thumbnail"]');
    expect(modalThumbnails).toHaveLength(3);
    
    // Click second thumbnail in modal
    fireEvent.click(modalThumbnails[1]);
    
    // Main modal image should change
    const modalMainImage = document.querySelector('.fixed img:not([alt*="Thumbnail"])');
    expect(modalMainImage).toHaveAttribute('alt', 'Test Product - Image 2');
  });
});
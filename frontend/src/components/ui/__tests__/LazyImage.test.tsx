import React from 'react';
import { render, screen, waitFor } from '@testing-library/react';
import { useInView } from 'react-intersection-observer';
import LazyImage from '../LazyImage';

// Mock react-intersection-observer
jest.mock('react-intersection-observer');

describe('LazyImage', () => {
  const mockUseInView = useInView as jest.MockedFunction<typeof useInView>;

  beforeEach(() => {
    jest.clearAllMocks();
  });

  it('should not load image when not in view', () => {
    mockUseInView.mockReturnValue({
      ref: jest.fn(),
      inView: false,
      entry: undefined,
    });

    render(<LazyImage src="/test-image.jpg" alt="Test Image" />);

    const img = screen.queryByAlt('Test Image');
    expect(img).not.toBeInTheDocument();
  });

  it('should load image when in view', async () => {
    mockUseInView.mockReturnValue({
      ref: jest.fn(),
      inView: true,
      entry: undefined,
    });

    render(<LazyImage src="/test-image.jpg" alt="Test Image" />);

    await waitFor(() => {
      const img = screen.getByAlt('Test Image');
      expect(img).toBeInTheDocument();
      expect(img).toHaveAttribute('src', '/test-image.jpg');
    });
  });

  it('should show loading placeholder initially', () => {
    mockUseInView.mockReturnValue({
      ref: jest.fn(),
      inView: true,
      entry: undefined,
    });

    const { container } = render(<LazyImage src="/test-image.jpg" alt="Test Image" />);

    const placeholder = container.querySelector('.animate-pulse');
    expect(placeholder).toBeInTheDocument();
  });

  it('should call onLoad callback when image loads', async () => {
    mockUseInView.mockReturnValue({
      ref: jest.fn(),
      inView: true,
      entry: undefined,
    });

    const onLoad = jest.fn();
    render(<LazyImage src="/test-image.jpg" alt="Test Image" onLoad={onLoad} />);

    const img = await screen.findByAlt('Test Image');
    
    // Simulate image load
    img.dispatchEvent(new Event('load'));

    await waitFor(() => {
      expect(onLoad).toHaveBeenCalled();
    });
  });

  it('should show error placeholder when image fails to load', async () => {
    mockUseInView.mockReturnValue({
      ref: jest.fn(),
      inView: true,
      entry: undefined,
    });

    const onError = jest.fn();
    render(<LazyImage src="/invalid-image.jpg" alt="Test Image" onError={onError} />);

    const img = await screen.findByAlt('Test Image');
    
    // Simulate image error
    img.dispatchEvent(new Event('error'));

    await waitFor(() => {
      expect(onError).toHaveBeenCalled();
      expect(screen.getByText('No Image')).toBeInTheDocument();
    });
  });

  it('should use custom threshold and rootMargin', () => {
    const mockRef = jest.fn();
    mockUseInView.mockReturnValue({
      ref: mockRef,
      inView: false,
      entry: undefined,
    });

    render(
      <LazyImage
        src="/test-image.jpg"
        alt="Test Image"
        threshold={0.5}
        rootMargin="200px"
      />
    );

    expect(mockUseInView).toHaveBeenCalledWith({
      threshold: 0.5,
      rootMargin: '200px',
      triggerOnce: true,
    });
  });
});

import React from 'react';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import PasswordChangeForm from '../PasswordChangeForm';
import { authService } from '../../../services/authApi';

// Mock the auth service
jest.mock('../../../services/authApi');
const mockedAuthService = authService as jest.Mocked<typeof authService>;

describe('PasswordChangeForm', () => {
  beforeEach(() => {
    jest.clearAllMocks();
  });

  it('renders password change form with all required fields', () => {
    render(<PasswordChangeForm />);
    
    expect(screen.getByRole('heading', { name: 'Change Password' })).toBeInTheDocument();
    expect(screen.getByLabelText('Current Password')).toBeInTheDocument();
    expect(screen.getByLabelText('New Password')).toBeInTheDocument();
    expect(screen.getByLabelText('Confirm New Password')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Change Password' })).toBeInTheDocument();
  });

  it('validates required fields', async () => {
    render(<PasswordChangeForm />);
    
    const submitButton = screen.getByRole('button', { name: 'Change Password' });
    fireEvent.click(submitButton);

    await waitFor(() => {
      expect(screen.getByText('Current password is required')).toBeInTheDocument();
      expect(screen.getByText('New password is required')).toBeInTheDocument();
      expect(screen.getByText('Please confirm your new password')).toBeInTheDocument();
    });
  });

  it('validates new password length', async () => {
    render(<PasswordChangeForm />);
    
    const newPasswordInput = screen.getByLabelText('New Password');
    const submitButton = screen.getByRole('button', { name: 'Change Password' });
    
    fireEvent.change(newPasswordInput, { target: { value: '123' } });
    fireEvent.click(submitButton);

    await waitFor(() => {
      expect(screen.getByText('Password must be at least 8 characters long')).toBeInTheDocument();
    });
  });

  it('validates new password is different from current', async () => {
    render(<PasswordChangeForm />);
    
    const currentPasswordInput = screen.getByLabelText('Current Password');
    const newPasswordInput = screen.getByLabelText('New Password');
    const submitButton = screen.getByRole('button', { name: 'Change Password' });
    
    fireEvent.change(currentPasswordInput, { target: { value: 'password123' } });
    fireEvent.change(newPasswordInput, { target: { value: 'password123' } });
    fireEvent.click(submitButton);

    await waitFor(() => {
      expect(screen.getByText('New password must be different from current password')).toBeInTheDocument();
    });
  });

  it('validates password confirmation match', async () => {
    render(<PasswordChangeForm />);
    
    const newPasswordInput = screen.getByLabelText('New Password');
    const confirmPasswordInput = screen.getByLabelText('Confirm New Password');
    const submitButton = screen.getByRole('button', { name: 'Change Password' });
    
    fireEvent.change(newPasswordInput, { target: { value: 'newpassword123' } });
    fireEvent.change(confirmPasswordInput, { target: { value: 'different123' } });
    fireEvent.click(submitButton);

    await waitFor(() => {
      expect(screen.getByText('Passwords do not match')).toBeInTheDocument();
    });
  });

  it('submits form with valid data', async () => {
    mockedAuthService.changePassword.mockResolvedValue({
      success: true,
      data: null,
    });

    render(<PasswordChangeForm />);
    
    const currentPasswordInput = screen.getByLabelText('Current Password');
    const newPasswordInput = screen.getByLabelText('New Password');
    const confirmPasswordInput = screen.getByLabelText('Confirm New Password');
    const submitButton = screen.getByRole('button', { name: 'Change Password' });
    
    fireEvent.change(currentPasswordInput, { target: { value: 'oldpassword123' } });
    fireEvent.change(newPasswordInput, { target: { value: 'newpassword123' } });
    fireEvent.change(confirmPasswordInput, { target: { value: 'newpassword123' } });
    fireEvent.click(submitButton);

    await waitFor(() => {
      expect(mockedAuthService.changePassword).toHaveBeenCalledWith({
        current_password: 'oldpassword123',
        password: 'newpassword123',
        password_confirmation: 'newpassword123',
      });
    });
  });

  it('shows success message after successful password change', async () => {
    mockedAuthService.changePassword.mockResolvedValue({
      success: true,
      data: null,
    });

    render(<PasswordChangeForm />);
    
    const currentPasswordInput = screen.getByLabelText('Current Password');
    const newPasswordInput = screen.getByLabelText('New Password');
    const confirmPasswordInput = screen.getByLabelText('Confirm New Password');
    const submitButton = screen.getByRole('button', { name: 'Change Password' });
    
    fireEvent.change(currentPasswordInput, { target: { value: 'oldpassword123' } });
    fireEvent.change(newPasswordInput, { target: { value: 'newpassword123' } });
    fireEvent.change(confirmPasswordInput, { target: { value: 'newpassword123' } });
    fireEvent.click(submitButton);

    await waitFor(() => {
      expect(screen.getByText('Password changed successfully!')).toBeInTheDocument();
    });

    // Form should be cleared after success
    expect(currentPasswordInput).toHaveValue('');
    expect(newPasswordInput).toHaveValue('');
    expect(confirmPasswordInput).toHaveValue('');
  });

  it('handles password change failure', async () => {
    mockedAuthService.changePassword.mockRejectedValue({
      response: {
        data: {
          message: 'Current password is incorrect',
        },
      },
    });

    render(<PasswordChangeForm />);
    
    const currentPasswordInput = screen.getByLabelText('Current Password');
    const newPasswordInput = screen.getByLabelText('New Password');
    const confirmPasswordInput = screen.getByLabelText('Confirm New Password');
    const submitButton = screen.getByRole('button', { name: 'Change Password' });
    
    fireEvent.change(currentPasswordInput, { target: { value: 'wrongpassword' } });
    fireEvent.change(newPasswordInput, { target: { value: 'newpassword123' } });
    fireEvent.change(confirmPasswordInput, { target: { value: 'newpassword123' } });
    fireEvent.click(submitButton);

    await waitFor(() => {
      expect(screen.getByText('Current password is incorrect')).toBeInTheDocument();
    });
  });

  it('shows loading state during submission', async () => {
    mockedAuthService.changePassword.mockImplementation(() => new Promise(resolve => setTimeout(resolve, 100)));

    render(<PasswordChangeForm />);
    
    const currentPasswordInput = screen.getByLabelText('Current Password');
    const newPasswordInput = screen.getByLabelText('New Password');
    const confirmPasswordInput = screen.getByLabelText('Confirm New Password');
    const submitButton = screen.getByRole('button', { name: 'Change Password' });
    
    fireEvent.change(currentPasswordInput, { target: { value: 'oldpassword123' } });
    fireEvent.change(newPasswordInput, { target: { value: 'newpassword123' } });
    fireEvent.change(confirmPasswordInput, { target: { value: 'newpassword123' } });
    fireEvent.click(submitButton);

    expect(screen.getByText('Changing Password...')).toBeInTheDocument();
    expect(submitButton).toBeDisabled();
  });
});
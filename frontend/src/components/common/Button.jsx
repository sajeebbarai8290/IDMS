import React from 'react';

/**
 * Reusable IDMS Government Button Component with loading state & icons.
 */
export const Button = ({
  children,
  variant = 'primary',
  size = 'md',
  isLoading = false,
  disabled = false,
  icon = null,
  onClick,
  type = 'button',
  className = '',
  ...props
}) => {
  const sizeClass = size === 'sm' ? 'btn-sm' : size === 'lg' ? 'btn-lg' : '';

  return (
    <button
      type={type}
      className={`btn btn-${variant} ${sizeClass} ${className} d-inline-flex align-items-center justify-content-center gap-2`}
      disabled={disabled || isLoading}
      onClick={onClick}
      {...props}
    >
      {isLoading ? (
        <>
          <span className="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
          <span>প্রক্রিয়াকরণ হচ্ছে...</span>
        </>
      ) : (
        <>
          {icon && <i className={`bi ${icon}`}></i>}
          {children}
        </>
      )}
    </button>
  );
};

export default Button;

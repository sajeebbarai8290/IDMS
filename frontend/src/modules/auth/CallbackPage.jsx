import React, { useEffect, useState } from 'react';
import { useNavigate, useSearchParams, Link } from 'react-router-dom';
import { keycloakService } from '../../api/keycloakService';
import { useAuth } from '../../hooks/useAuth';

/**
 * Keycloak OIDC Authorization Code Flow Callback Handler
 *
 * Handles the redirect from Keycloak at /auth/callback.
 * Verifies OAuth state, exchanges the authorization code + PKCE code_verifier
 * for access, refresh, and ID tokens, synchronizes the auth state,
 * and navigates the user to their destination.
 */
export const CallbackPage = () => {
  const [searchParams] = useSearchParams();
  const navigate = useNavigate();
  const { syncSession } = useAuth();
  const [status, setStatus] = useState('processing'); // 'processing' | 'error'
  const [errorMessage, setErrorMessage] = useState('');

  useEffect(() => {
    const processCallback = async () => {
      const code = searchParams.get('code');
      const state = searchParams.get('state');
      const error = searchParams.get('error');
      const errorDescription = searchParams.get('error_description');

      // 1. Handle Keycloak-reported errors (e.g. user cancelled, consent refused)
      if (error) {
        setStatus('error');
        setErrorMessage(
          errorDescription || `Keycloak প্রমাণীকরণ ব্যর্থ হয়েছে (${error})। অনুগ্রহ করে পুনরায় চেষ্টা করুন।`
        );
        return;
      }

      // 2. Validate mandatory OIDC query parameters
      if (!code || !state) {
        setStatus('error');
        setErrorMessage('অবৈধ প্রমাণীকরণ প্রতিক্রিয়া: কোড বা স্টেট প্যারামিটার অনুপস্থিত।');
        return;
      }

      try {
        // 3. Exchange authorization code for tokens using PKCE verifier
        await keycloakService.handleCallback(code, state);

        // 4. Update React AuthContext state with fresh tokens
        if (syncSession) {
          await syncSession();
        }

        setStatus('success');

        // 5. Navigate to saved route or dashboard (guarded against malformed strings)
        let returnTo = sessionStorage.getItem('idms_return_to') || '/dashboard';
        sessionStorage.removeItem('idms_return_to');
        if (typeof returnTo !== 'string' || !returnTo.startsWith('/') || returnTo.includes('[object')) {
          returnTo = '/dashboard';
        }
        navigate(returnTo, { replace: true });
      } catch (err) {
        console.error('OIDC PKCE callback error:', err);
        setStatus('error');
        setErrorMessage(
          err.message || 'Keycloak ওআইডিসি টোকেন বিনিময়ে সমস্যা দেখা দিয়েছে।'
        );
      }
    };

    processCallback();
  }, [searchParams, navigate, syncSession]);

  return (
    <div className="container min-vh-100 d-flex align-items-center justify-content-center py-5">
      <div className="row justify-content-center w-100">
        <div className="col-12 col-md-7 col-lg-5">
          <div className="card shadow-lg border-0 rounded-4 overflow-hidden">
            <div className="bg-success p-4 text-center text-white">
              <div
                className="rounded-circle bg-white text-success d-inline-flex align-items-center justify-content-center mb-3 shadow"
                style={{ width: 64, height: 64 }}
              >
                <i className={`bi ${status === 'error' ? 'bi-exclamation-triangle-fill text-danger' : 'bi-shield-lock-fill'} fs-2`}></i>
              </div>
              <h5 className="fw-bold mb-1">কেন্দ্রীয় নিরাপত্তা যাচাইকরণ</h5>
              <div className="small opacity-75">Keycloak OpenID Connect & PKCE</div>
            </div>

            <div className="card-body p-4 p-md-5 text-center">
              {status === 'processing' && (
                <div>
                  <div className="spinner-border text-success mb-3" style={{ width: '3rem', height: '3rem' }} role="status">
                    <span className="visually-hidden">যাচাই করা হচ্ছে...</span>
                  </div>
                  <h6 className="fw-bold text-dark mb-2">নিরাপদ টোকেন যাচাই ও বিনিময় চলছে...</h6>
                  <p className="text-muted small mb-0">
                    অনুগ্রহ করে অপেক্ষা করুন। আপনার ডিজিটাল পরিচয় ও প্রশাসনিক অধিক্ষেত্র যাচাই করা হচ্ছে।
                  </p>
                </div>
              )}

              {status === 'error' && (
                <div>
                  <div className="alert alert-danger border-0 text-start mb-4">
                    <div className="d-flex gap-2">
                      <i className="bi bi-x-circle-fill fs-5 mt-1"></i>
                      <div>
                        <strong>প্রমাণীকরণ ব্যর্থ হয়েছে!</strong>
                        <div className="small mt-1">{errorMessage}</div>
                      </div>
                    </div>
                  </div>

                  <div className="d-grid gap-2">
                    <Link to="/login" className="btn btn-success">
                      <i className="bi bi-arrow-left me-1"></i> লগইন পাতায় ফিরে যান
                    </Link>
                  </div>
                </div>
              )}
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};

export default CallbackPage;

import React, { useState } from 'react';
import { useNavigate, useLocation } from 'react-router-dom';
import { useAuth } from '../../hooks/useAuth';
import Button from '../../components/common/Button';

export const LoginPage = () => {
  const { login, devLogin, isAuthenticated } = useAuth();
  const navigate = useNavigate();
  const location = useLocation();
  const [selectedRole, setSelectedRole] = useState('ROLE_DRRO');
  const [selectedTier, setSelectedTier] = useState('DISTRICT');

  const from = location.state?.from?.pathname || '/dashboard';

  React.useEffect(() => {
    if (isAuthenticated) {
      navigate(from, { replace: true });
    }
  }, [isAuthenticated, navigate, from]);

  const handleDevLogin = () => {
    devLogin(selectedRole, selectedTier);
    navigate(from, { replace: true });
  };

  return (
    <div className="container min-vh-100 d-flex align-items-center justify-content-center py-5">
      <div className="row justify-content-center w-100">
        <div className="col-12 col-md-8 col-lg-6 col-xl-5">
          <div className="card shadow-lg border-0 rounded-4 overflow-hidden">
            <div className="bg-success p-4 text-center text-white">
              <div className="rounded-circle bg-white text-success d-inline-flex align-items-center justify-content-center mb-3 shadow" style={{ width: 64, height: 64 }}>
                <i className="bi bi-shield-check fs-2"></i>
              </div>
              <h4 className="fw-bold mb-1">সমন্বিত দুর্যোগ ব্যবস্থাপনা সিস্টেম</h4>
              <div className="small opacity-75">Integrated Disaster Management System (IDMS)</div>
              <div className="badge bg-white text-success mt-2">গণপ্রজাতন্ত্রী বাংলাদেশ সরকার</div>
            </div>

            <div className="card-body p-4 p-md-5">
              <div className="alert alert-info border-0 d-flex gap-2 mb-4">
                <i className="bi bi-info-circle-fill fs-5 mt-1"></i>
                <small className="text-secondary">
                  এই সিস্টেমে প্রবেশাধিকার শুধুমাত্র অনুমোদিত সরকারি কর্মকর্তা ও মাঠপর্যায়ের কর্মীদের জন্য সীমাবদ্ধ। কেন্দ্রীয় <strong>Keycloak Identity Provider</strong> ও <strong>MFA</strong> এর মাধ্যমে প্রবেশ নিশ্চিত করা হয়।
                </small>
              </div>

              {/* Official Keycloak SSO Login */}
              <div className="d-grid mb-4">
                <Button
                  variant="success"
                  size="lg"
                  icon="bi-shield-lock-fill"
                  onClick={login}
                >
                  সরকারি SSO লগইন (Keycloak)
                </Button>
                <small className="text-center text-muted mt-2">
                  OpenID Connect + PKCE ও দ্বি-স্তরীয় যাচাইকরণ (MFA)
                </small>
              </div>

              <div className="position-relative my-4">
                <hr />
                <span className="position-absolute top-50 start-50 translate-middle bg-white px-3 text-muted small">
                  অথবা স্থানীয় ডেভেলপমেন্ট টেস্ট
                </span>
              </div>

              {/* Dev/Local Role Simulator */}
              <div className="bg-light p-3 rounded-3 border">
                <div className="fw-bold text-secondary small mb-2 d-flex align-items-center gap-1">
                  <i className="bi bi-person-badge"></i>
                  <span>টেস্টিং প্রোফাইল নির্বাচন (Role Simulation)</span>
                </div>

                <div className="mb-2">
                  <label className="form-label small text-muted mb-1">সিস্টেম ভূমিকা (System Role):</label>
                  <select
                    className="form-select form-select-sm"
                    value={selectedRole}
                    onChange={(e) => {
                      setSelectedRole(e.target.value);
                      if (e.target.value === 'ROLE_MINISTRY_EXECUTIVE') setSelectedTier('NATIONAL');
                      else if (e.target.value === 'ROLE_DRRO' || e.target.value === 'ROLE_DEPUTY_COMMISSIONER') setSelectedTier('DISTRICT');
                      else if (e.target.value === 'ROLE_UNO' || e.target.value === 'ROLE_PIO') setSelectedTier('UPAZILA');
                      else if (e.target.value === 'ROLE_UP_CHAIRMAN') setSelectedTier('UNION');
                      else setSelectedTier('FIELD');
                    }}
                  >
                    <option value="ROLE_MINISTRY_EXECUTIVE">জাতীয় / সচিব (ROLE_MINISTRY_EXECUTIVE)</option>
                    <option value="ROLE_DDM_DIRECTOR_GENERAL">মহাপরিচালক, ডিডিএম (ROLE_DDM_DIRECTOR_GENERAL)</option>
                    <option value="ROLE_DEPUTY_COMMISSIONER">জেলা প্রশাসক - ডিসি (ROLE_DEPUTY_COMMISSIONER)</option>
                    <option value="ROLE_DRRO">জেলা ত্রাণ ও পুনর্বাসন কর্মকর্তা - ডিআরআরও (ROLE_DRRO)</option>
                    <option value="ROLE_UNO">উপজেলা নির্বাহী অফিসার - ইউএনও (ROLE_UNO)</option>
                    <option value="ROLE_PIO">প্রকল্প বাস্তবায়ন কর্মকর্তা - পিআইও (ROLE_PIO)</option>
                    <option value="ROLE_UP_CHAIRMAN">ইউনিয়ন পরিষদ চেয়ারম্যান (ROLE_UP_CHAIRMAN)</option>
                    <option value="ROLE_FIELD_ENUMERATOR">মাঠ তথ্য সংগ্রহকারী (ROLE_FIELD_ENUMERATOR)</option>
                    <option value="ROLE_NATIONAL_AUDITOR">সিএজি / অডিটর (ROLE_NATIONAL_AUDITOR)</option>
                  </select>
                </div>

                <Button
                  variant="outline-secondary"
                  size="sm"
                  className="w-100 mt-2"
                  icon="bi-box-arrow-in-right"
                  onClick={handleDevLogin}
                >
                  নির্বাচিত ভূমিকায় প্রবেশ করুন (Dev Mode)
                </Button>
              </div>
            </div>

            <div className="card-footer bg-light text-center py-3 border-top">
              <small className="text-muted">
                জরুরি প্রযুক্তিগত সহায়তায়: ict@modmr.gov.bd
              </small>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};

export default LoginPage;

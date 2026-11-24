import React, { useState } from 'react';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { Link } from 'react-router-dom';
import beneficiaryApi from '../../../api/beneficiaryApi';

export const ImportExportPage = () => {
  const queryClient = useQueryClient();
  const [file, setFile] = useState(null);
  const [csvText, setCsvText] = useState('');
  const [importResult, setImportResult] = useState(null);
  const [activeTab, setActiveTab] = useState('upload'); // 'upload' | 'paste'

  const importFileMutation = useMutation({
    mutationFn: (formData) => beneficiaryApi.importBeneficiaries(formData),
    onSuccess: (res) => {
      setImportResult(res?.data || res);
      queryClient.invalidateQueries(['households']);
    },
    onError: (err) => {
      setImportResult(err.response?.data || { success: false, message: 'ইমপোর্ট ব্যর্থ হয়েছে।' });
    },
  });

  const importTextMutation = useMutation({
    mutationFn: (text) => beneficiaryApi.importCsvData(text),
    onSuccess: (res) => {
      setImportResult(res?.data || res);
      queryClient.invalidateQueries(['households']);
    },
    onError: (err) => {
      setImportResult(err.response?.data || { success: false, message: 'ইমপোর্ট ব্যর্থ হয়েছে।' });
    },
  });

  const handleFileUpload = (e) => {
    e.preventDefault();
    if (!file) return;
    const formData = new FormData();
    formData.append('file', file);
    importFileMutation.mutate(formData);
  };

  const handleTextUpload = (e) => {
    e.preventDefault();
    if (!csvText.trim()) return;
    importTextMutation.mutate(csvText);
  };

  const isSubmitting = importFileMutation.isLoading || importTextMutation.isLoading;

  return (
    <div className="container py-2" style={{ maxWidth: '960px' }}>
      {/* Title */}
      <div className="d-flex justify-content-between align-items-center mb-4">
        <div>
          <h4 className="fw-bold mb-1">
            <i className="bi bi-file-earmark-excel text-success me-2"></i>
            উপকারভোগী বাল্ক ইমপোর্ট ও এক্সপোর্ট (Bulk Import & Export)
          </h4>
          <p className="text-muted small mb-0">একসাথে একাধিক পরিবার নিবন্ধন ও অনুমোদিত তালিকা ডাউনলোড</p>
        </div>
        <Link to="/beneficiary/households" className="btn btn-outline-secondary btn-sm">
          মাস্টার রেজিস্ট্রি
        </Link>
      </div>

      <div className="row g-4">
        {/* Import Section */}
        <div className="col-lg-7">
          <div className="card shadow-sm border-0 h-100">
            <div className="card-header bg-white py-3 border-0">
              <h5 className="fw-bold mb-0 text-primary">
                <i className="bi bi-cloud-arrow-up me-2"></i>
                সিএসভি / এক্সেল ইমপোর্ট
              </h5>
            </div>
            <div className="card-body pt-0">
              {/* Tab Selector */}
              <div className="btn-group btn-group-sm w-100 mb-3">
                <button
                  type="button"
                  className={`btn ${activeTab === 'upload' ? 'btn-primary' : 'btn-outline-primary'}`}
                  onClick={() => setActiveTab('upload')}
                >
                  <i className="bi bi-file-earmark-arrow-up me-1"></i> ফাইল আপলোড
                </button>
                <button
                  type="button"
                  className={`btn ${activeTab === 'paste' ? 'btn-primary' : 'btn-outline-primary'}`}
                  onClick={() => setActiveTab('paste')}
                >
                  <i className="bi bi-textarea-t me-1"></i> সরাসরি সিএসভি টেক্সট পেস্ট
                </button>
              </div>

              {activeTab === 'upload' ? (
                <form onSubmit={handleFileUpload}>
                  <div className="mb-3">
                    <label className="form-label small fw-bold">CSV ফাইল নির্বাচন করুন (.csv)</label>
                    <input
                      type="file"
                      className="form-control"
                      accept=".csv,text/csv"
                      onChange={(e) => setFile(e.target.files[0])}
                      required
                    />
                    <small className="text-muted">ইউনিকোড (UTF-8) ফরম্যাটের সিএসভি ফাইল সমর্থনযোগ্য।</small>
                  </div>
                  <button type="submit" className="btn btn-success w-100" disabled={!file || isSubmitting}>
                    {isSubmitting ? 'প্রসেস হচ্ছে...' : 'ফাইল আপলোড ও যাচাই করুন'}
                  </button>
                </form>
              ) : (
                <form onSubmit={handleTextUpload}>
                  <div className="mb-3">
                    <label className="form-label small fw-bold">সিএসভি ডাটা পেস্ট করুন</label>
                    <textarea
                      className="form-control font-monospace small"
                      rows="7"
                      placeholder="hoh_name_en,hoh_name_bn,nid,mobile,gender,dob,admin_code,village,income..."
                      value={csvText}
                      onChange={(e) => setCsvText(e.target.value)}
                      required
                    ></textarea>
                  </div>
                  <button type="submit" className="btn btn-success w-100" disabled={!csvText.trim() || isSubmitting}>
                    {isSubmitting ? 'প্রসেস হচ্ছে...' : 'ডাটা ইমপোর্ট সম্পন্ন করুন'}
                  </button>
                </form>
              )}

              {/* Import Results Banner */}
              {importResult && (
                <div className={`alert mt-4 ${importResult.created > 0 ? 'alert-success' : 'alert-danger'}`}>
                  <h6 className="fw-bold mb-1">
                    <i
                      className={`bi ${importResult.created > 0 ? 'bi-check-circle-fill' : 'bi-x-circle-fill'} me-2`}
                    ></i>
                    {importResult.message || 'ইমপোর্ট ফলাফল:'}
                  </h6>
                  <p className="mb-1 small">
                    মোট প্রসেসকৃত: <strong>{importResult.processed || 0}</strong> • সফল নিবন্ধন:{' '}
                    <strong>{importResult.created || 0}</strong> • ত্রুটি:{' '}
                    <strong>{importResult.failed || 0}</strong>
                  </p>

                  {importResult.errors && importResult.errors.length > 0 && (
                    <div className="mt-3">
                      <small className="fw-bold d-block mb-1 text-danger">ত্রুটিযুক্ত রেকর্ডের বিবরণ:</small>
                      <div className="table-responsive bg-white rounded border">
                        <table className="table table-sm table-bordered mb-0 small">
                          <thead className="table-light">
                            <tr>
                              <th>লাইন নং</th>
                              <th>ফিল্ড</th>
                              <th>কারণের বিবরণ</th>
                            </tr>
                          </thead>
                          <tbody>
                            {importResult.errors.map((err, idx) => (
                              <tr key={idx}>
                                <td className="text-center font-monospace">{err.row}</td>
                                <td>{err.field}</td>
                                <td className="text-danger">{err.message}</td>
                              </tr>
                            ))}
                          </tbody>
                        </table>
                      </div>
                    </div>
                  )}
                </div>
              )}
            </div>
          </div>
        </div>

        {/* Template & Export Section */}
        <div className="col-lg-5">
          {/* Download Sample Template */}
          <div className="card shadow-sm border-0 mb-4">
            <div className="card-header bg-white py-3 border-0">
              <h5 className="fw-bold mb-0 text-success">
                <i className="bi bi-file-earmark-arrow-down me-2"></i>
                নমুনা টেমপ্লেট
              </h5>
            </div>
            <div className="card-body pt-0">
              <p className="small text-muted mb-3">
                সঠিক ফরম্যাটে তথ্য প্রস্তুত করার জন্য নিচের নির্ধারিত সরকারি সিএসভি টেমপ্লেটটি ডাউনলোড করুন:
              </p>
              <a
                href={beneficiaryApi.downloadTemplateUrl()}
                className="btn btn-outline-success w-100 mb-2"
                target="_blank"
                rel="noopener noreferrer"
              >
                <i className="bi bi-download me-2"></i>
                সিএসভি টেমপ্লেট ডাউনলোড করুন (.csv)
              </a>
              <div className="bg-light p-2 rounded small text-muted">
                <strong>কলামের ক্রম:</strong> hoh_name_en, hoh_name_bn, nid, mobile, gender, dob, admin_code, village,
                present_address, income, member_count, has_elderly, has_disabled, is_displaced
              </div>
            </div>
          </div>

          {/* Export Muster Roll */}
          <div className="card shadow-sm border-0">
            <div className="card-header bg-white py-3 border-0">
              <h5 className="fw-bold mb-0 text-dark">
                <i className="bi bi-file-earmark-spreadsheet me-2"></i>
                অনুমোদিত মাস্টার রোল এক্সপোর্ট
              </h5>
            </div>
            <div className="card-body pt-0">
              <p className="small text-muted mb-3">
                ত্রাণ বিতরণের জন্য ফিল্ড অফিসারদের স্বাক্ষর গ্রহণ উপযোগী সরকারি মাস্টার রোল এক্সেল/সিএসভি ফরম্যাটে ডাউনলোড করুন:
              </p>
              <a
                href={beneficiaryApi.exportUrl()}
                className="btn btn-primary w-100"
                target="_blank"
                rel="noopener noreferrer"
              >
                <i className="bi bi-file-earmark-arrow-down-fill me-2"></i>
                সম্পূর্ণ মাস্টার রোল এক্সপোর্ট (CSV)
              </a>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};

export default ImportExportPage;

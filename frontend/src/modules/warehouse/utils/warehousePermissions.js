const getUserRoles = (user) => {
  const roles = [...(user?.roles || [])];
  if (user?.role_code && !roles.includes(user.role_code)) {
    roles.push(user.role_code);
  }
  return roles;
};

const hasAny = (user, allowed) => getUserRoles(user).some((r) => allowed.includes(r));

export const isWarehouseBlackout = (user) => getUserRoles(user).includes('ROLE_SUPER_ADMIN');

export const canManageFacilities = (user) =>
  hasAny(user, [
    'ROLE_SUPER_ADMIN',
    'ROLE_DDM_DIRECTOR_GENERAL',
    'ROLE_DDM_RELIEF_DIRECTOR',
    'ROLE_DEPUTY_COMMISSIONER',
    'ROLE_DRRO',
    'ROLE_UNO',
    'ROLE_PIO',
  ]);

export const canManageItems = (user) =>
  hasAny(user, [
    'ROLE_SUPER_ADMIN',
    'ROLE_DDM_DIRECTOR_GENERAL',
    'ROLE_DDM_RELIEF_DIRECTOR',
    'ROLE_MINISTRY_EXECUTIVE',
    'ROLE_DRRO',
    'ROLE_WAREHOUSE_MANAGER',
    'ROLE_WAREHOUSE_OFFICER',
  ]);

export const canReceiveStock = (user) =>
  !isWarehouseBlackout(user) &&
  hasAny(user, ['ROLE_DRRO', 'ROLE_PIO', 'ROLE_WAREHOUSE_MANAGER', 'ROLE_WAREHOUSE_OFFICER']);

export const canVerifyGrn = (user) =>
  !isWarehouseBlackout(user) &&
  hasAny(user, [
    'ROLE_DDM_DIRECTOR_GENERAL',
    'ROLE_DDM_RELIEF_DIRECTOR',
    'ROLE_DEPUTY_COMMISSIONER',
    'ROLE_DRRO',
    'ROLE_UNO',
    'ROLE_PIO',
    'ROLE_WAREHOUSE_MANAGER',
    'ROLE_WAREHOUSE_OFFICER',
  ]);

export const canRequestTransfer = (user) =>
  !isWarehouseBlackout(user) &&
  hasAny(user, [
    'ROLE_DDM_DIRECTOR_GENERAL',
    'ROLE_DDM_RELIEF_DIRECTOR',
    'ROLE_DIVISIONAL_COMMISSIONER',
    'ROLE_DEPUTY_COMMISSIONER',
    'ROLE_DRRO',
    'ROLE_UNO',
    'ROLE_PIO',
    'ROLE_WAREHOUSE_MANAGER',
    'ROLE_WAREHOUSE_OFFICER',
  ]);

export const canApproveTransfer = (user) =>
  !isWarehouseBlackout(user) &&
  hasAny(user, [
    'ROLE_DDM_DIRECTOR_GENERAL',
    'ROLE_DDM_RELIEF_DIRECTOR',
    'ROLE_DIVISIONAL_COMMISSIONER',
    'ROLE_DEPUTY_COMMISSIONER',
    'ROLE_DRRO',
    'ROLE_UNO',
    'ROLE_PIO',
  ]);

export const canIssueWarehouseStock = (user) =>
  !isWarehouseBlackout(user) &&
  hasAny(user, ['ROLE_DRRO', 'ROLE_PIO', 'ROLE_WAREHOUSE_MANAGER', 'ROLE_WAREHOUSE_OFFICER']);

export const canWriteOffStock = (user) =>
  !isWarehouseBlackout(user) &&
  hasAny(user, ['ROLE_DDM_DIRECTOR_GENERAL', 'ROLE_DDM_RELIEF_DIRECTOR', 'ROLE_DEPUTY_COMMISSIONER', 'ROLE_DRRO']);

export const canExportWarehouseLedger = (user) =>
  hasAny(user, [
    'ROLE_SUPER_ADMIN',
    'ROLE_MINISTRY_EXECUTIVE',
    'ROLE_NEOC_DIRECTOR',
    'ROLE_NATIONAL_AUDITOR',
    'ROLE_DDM_DIRECTOR_GENERAL',
    'ROLE_DDM_RELIEF_DIRECTOR',
    'ROLE_DDM_PLANNING_OFFICER',
    'ROLE_DDM_PLANNING_DIRECTOR',
    'ROLE_DIVISIONAL_COMMISSIONER',
    'ROLE_DEPUTY_COMMISSIONER',
    'ROLE_DRRO',
    'ROLE_UNO',
    'ROLE_PIO',
    'ROLE_UP_CHAIRMAN',
    'ROLE_UP_SECRETARY',
    'ROLE_TAG_OFFICER',
    'ROLE_WAREHOUSE_MANAGER',
    'ROLE_WAREHOUSE_OFFICER',
  ]);

export const getTodayIsoDate = () => new Date().toISOString().slice(0, 10);

export const WAREHOUSE_TIER_LABELS = {
  CENTRAL_CSD: { bn: 'কেন্দ্রীয় সংরক্ষণাগার (CSD)', en: 'Central CSD', variant: 'primary' },
  REGIONAL_LSD: { bn: 'আঞ্চলিক গুদাম (LSD)', en: 'Regional LSD', variant: 'info' },
  DISTRICT_GODOWN: { bn: 'জেলা গুদাম', en: 'District Godown', variant: 'success' },
  UPAZILA_STORE: { bn: 'উপজেলা স্টোর', en: 'Upazila Store', variant: 'secondary' },
};

export const RECEIPT_STATUS_META = {
  PENDING_VERIFICATION: { bn: 'যাচাই অপেক্ষমাণ', en: 'Pending Verification', variant: 'warning' },
  VERIFIED: { bn: 'যাচাইকৃত ও মজুদযুক্ত', en: 'Verified', variant: 'success' },
  REJECTED: { bn: 'প্রত্যাখ্যাত', en: 'Rejected', variant: 'danger' },
};

export const TRANSFER_STATUS_META = {
  REQUESTED: { bn: 'অনুরোধকৃত', en: 'Requested', variant: 'warning' },
  APPROVED: { bn: 'অনুমোদিত', en: 'Approved', variant: 'primary' },
  DISPATCHED: { bn: 'প্রেরিত (পথে)', en: 'Dispatched', variant: 'info' },
  RECEIVED: { bn: 'গৃহীত (সম্পন্ন)', en: 'Received', variant: 'success' },
  CANCELLED: { bn: 'বাতিল', en: 'Cancelled', variant: 'dark' },
};

export const TX_TYPE_LABELS = {
  OPENING_STOCK: { bn: 'প্রারম্ভিক মজুদ', en: 'Opening Stock', variant: 'primary' },
  STOCK_IN_GRN: { bn: 'জিআরএন গ্রহণ', en: 'GRN Stock In', variant: 'success' },
  INTER_WAREHOUSE_TRANSFER: { bn: 'আন্তঃগুদাম স্থানান্তর', en: 'Warehouse Transfer', variant: 'info' },
  STOCK_ISSUE: { bn: 'বরাদ্দ ইস্যু', en: 'Allocation Issue', variant: 'warning' },
  DISTRIBUTION_ISSUE: { bn: 'উপকারভোগী বিতরণ', en: 'Beneficiary Issue', variant: 'warning' },
  RETURN_IN: { bn: 'ফেরত গ্রহণ', en: 'Supply Return', variant: 'secondary' },
  ALLOCATION_RETURN: { bn: 'ভাউচার বাতিল ফেরত', en: 'Void Reversal', variant: 'secondary' },
  STOCK_ADJUSTMENT: { bn: 'মজুদ সমন্বয়', en: 'Stock Adjustment', variant: 'dark' },
  WRITE_OFF_DAMAGED: { bn: 'ক্ষতিগ্রস্ত রাইট-অফ', en: 'Damaged Write-Off', variant: 'danger' },
  EXPIRED_STOCK: { bn: 'মেয়াদোত্তীর্ণ কোয়ারেন্টাইন', en: 'Expired Stock', variant: 'danger' },
};

export const formatBdt = (value) => {
  const num = Number(value || 0);
  return `৳ ${num.toLocaleString('en-BD', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
};

export const formatWarehouseApiError = (err, fallback) => {
  const resp = err?.response?.data;
  if (resp?.errors && typeof resp.errors === 'object') {
    const flat = Object.values(resp.errors).flat().join(' ');
    if (flat) return flat;
  }
  return resp?.message || fallback;
};

export const triggerWarehouseBlobDownload = (blobData, filename) => {
  const url = window.URL.createObjectURL(new Blob([blobData]));
  const link = document.createElement('a');
  link.href = url;
  link.setAttribute('download', filename);
  document.body.appendChild(link);
  link.click();
  link.remove();
  window.URL.revokeObjectURL(url);
};

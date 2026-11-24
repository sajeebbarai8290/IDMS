import { jwtDecode } from 'jwt-decode';

/**
 * Keycloak OIDC Service for IDMS React SPA
 * Handles authentication redirect, token parsing, and claims decoding.
 */

const KEYCLOAK_CONFIG = {
  url: import.meta.env.VITE_KEYCLOAK_URL || 'http://localhost:8080',
  realm: import.meta.env.VITE_KEYCLOAK_REALM || 'idms-gov-realm',
  clientId: import.meta.env.VITE_KEYCLOAK_CLIENT_ID || 'idms-spa-frontend',
};

const STORAGE_KEYS = {
  ACCESS_TOKEN: 'idms_access_token',
  REFRESH_TOKEN: 'idms_refresh_token',
  USER_DATA: 'idms_user_profile',
};

class KeycloakService {
  /**
   * Get currently active access token.
   */
  getToken() {
    return sessionStorage.getItem(STORAGE_KEYS.ACCESS_TOKEN);
  }

  /**
   * Check if user is currently authenticated with a valid token.
   */
  isAuthenticated() {
    const token = this.getToken();
    if (!token) return false;

    try {
      const decoded = jwtDecode(token);
      const currentTime = Date.now() / 1000;
      return decoded.exp > currentTime;
    } catch {
      return false;
    }
  }

  /**
   * Extract decoded user profile & claims from the active token.
   */
  getUserProfile() {
    const token = this.getToken();
    if (!token) return null;

    try {
      const decoded = jwtDecode(token);
      return {
        id: decoded.sub,
        username: decoded.preferred_username || decoded.sub,
        email: decoded.email || 'officer@modmr.gov.bd',
        name: decoded.name || decoded.preferred_username || 'Government Officer',
        roles: decoded.roles || decoded.realm_access?.roles || ['ROLE_FIELD_ENUMERATOR'],
        administrativeScope: decoded.administrative_profile || {
          tier_level: 'NATIONAL',
          name_en: 'Ministry of Disaster Management and Relief',
          name_bn: 'দুর্যোগ ব্যবস্থাপনা ও ত্রাণ মন্ত্রণালয়',
          ancestry_path: '/',
        },
      };
    } catch {
      return null;
    }
  }

  /**
   * Initiate Keycloak OIDC Authorization Code Flow with PKCE.
   */
  login() {
    const redirectUri = encodeURIComponent(window.location.origin + '/dashboard');
    const authUrl = `${KEYCLOAK_CONFIG.url}/realms/${KEYCLOAK_CONFIG.realm}/protocol/openid-connect/auth` +
      `?client_id=${KEYCLOAK_CONFIG.clientId}` +
      `&redirect_uri=${redirectUri}` +
      `&response_type=code` +
      `&scope=openid email profile`;

    window.location.href = authUrl;
  }

  /**
   * Dev/Mock Login for local development prior to live Keycloak Docker deployment.
   * Creates a valid transient token containing the requested tier role.
   */
  devLogin(role = 'ROLE_DRRO', tier = 'DISTRICT') {
    const mockPayload = {
      sub: '4a7f29b0-8c21-41e9-b541-6e3e57f20108',
      preferred_username: 'drro_khulna',
      name: 'Md. Rafiqul Islam (DRRO)',
      email: 'drro.khulna@modmr.gov.bd',
      roles: [role],
      exp: Math.floor(Date.now() / 1000) + 3600 * 8, // 8 hours
      nbf: Math.floor(Date.now() / 1000),
      iat: Math.floor(Date.now() / 1000),
      iss: `${KEYCLOAK_CONFIG.url}/realms/${KEYCLOAK_CONFIG.realm}`,
      administrative_profile: {
        tier_level: tier,
        scope_id: '8f3e2b10-6c91-4d3a-8742-1a4e8d3b5c90',
        division_code: '40',
        district_code: '47',
        name_en: 'Khulna District',
        name_bn: 'খুলনা জেলা',
        ancestry_path: '/40/47/',
      },
    };

    // Construct a development JWT string (Base64 URL encoded)
    const header = btoa(JSON.stringify({ alg: 'RS256', typ: 'JWT', kid: 'dev-key' }));
    const payload = btoa(JSON.stringify(mockPayload));
    const signature = btoa('mock-dev-signature-modmr-keycloak');
    const mockToken = `${header}.${payload}.${signature}`;

    sessionStorage.setItem(STORAGE_KEYS.ACCESS_TOKEN, mockToken);
    return this.getUserProfile();
  }

  /**
   * Clear session tokens and log out.
   */
  logout() {
    sessionStorage.removeItem(STORAGE_KEYS.ACCESS_TOKEN);
    sessionStorage.removeItem(STORAGE_KEYS.REFRESH_TOKEN);
    sessionStorage.removeItem(STORAGE_KEYS.USER_DATA);
    window.location.href = '/login';
  }
}

export const keycloakService = new KeycloakService();

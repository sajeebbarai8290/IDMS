import { jwtDecode } from 'jwt-decode';

/**
 * Keycloak OIDC Service for IDMS React SPA
 * Implements RFC 7636 Authorization Code Flow with PKCE (Proof Key for Code Exchange),
 * secure session token management, silent refresh, and OIDC end-session logout.
 *
 * Security Rules:
 * - Public client: No client secrets stored or exposed in frontend.
 * - PKCE S256 code challenge used on every authorization request.
 * - Tokens stored in sessionStorage (cleared on browser/tab close).
 */

const KEYCLOAK_CONFIG = {
  url: import.meta.env.VITE_KEYCLOAK_URL || 'http://localhost:8080',
  realm: import.meta.env.VITE_KEYCLOAK_REALM || 'idms-gov-realm',
  clientId: import.meta.env.VITE_KEYCLOAK_CLIENT_ID || 'idms-spa-frontend',
};

const STORAGE_KEYS = {
  ACCESS_TOKEN: 'idms_access_token',
  REFRESH_TOKEN: 'idms_refresh_token',
  ID_TOKEN: 'idms_id_token',
  CODE_VERIFIER: 'idms_pkce_verifier',
  OAUTH_STATE: 'idms_oauth_state',
  USER_DATA: 'idms_user_profile',
};

class KeycloakService {
  /**
   * Generate cryptographically random string for PKCE and state.
   */
  generateRandomString(length = 64) {
    const charset = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-._~';
    const array = new Uint8Array(length);
    window.crypto.getRandomValues(array);
    return Array.from(array, (byte) => charset[byte % charset.length]).join('');
  }

  /**
   * Calculate SHA-256 hash and return base64url string.
   */
  async generateCodeChallenge(verifier) {
    const encoder = new TextEncoder();
    const data = encoder.encode(verifier);
    const digest = await window.crypto.subtle.digest('SHA-256', data);
    return btoa(String.fromCharCode(...new Uint8Array(digest)))
      .replace(/\+/g, '-')
      .replace(/\//g, '_')
      .replace(/=+$/, '');
  }

  /**
   * Initiate Keycloak OIDC Authorization Code Flow with PKCE.
   */
  async login() {
    const verifier = this.generateRandomString(64);
    const state = this.generateRandomString(32);
    const challenge = await this.generateCodeChallenge(verifier);

    // Save verifier and state in session
    sessionStorage.setItem(STORAGE_KEYS.CODE_VERIFIER, verifier);
    sessionStorage.setItem(STORAGE_KEYS.OAUTH_STATE, state);

    const redirectUri = encodeURIComponent(`${window.location.origin}/auth/callback`);

    const authUrl =
      `${KEYCLOAK_CONFIG.url}/realms/${KEYCLOAK_CONFIG.realm}/protocol/openid-connect/auth` +
      `?client_id=${KEYCLOAK_CONFIG.clientId}` +
      `&redirect_uri=${redirectUri}` +
      `&response_type=code` +
      `&scope=openid%20email%20profile` +
      `&state=${state}` +
      `&code_challenge=${challenge}` +
      `&code_challenge_method=S256`;

    window.location.href = authUrl;
  }

  /**
   * Handle OIDC Authorization Code exchange at /auth/callback.
   *
   * @param {string} code
   * @param {string} state
   * @returns {Promise<object>} Decoded user profile
   */
  async handleCallback(code, state) {
    const savedState = sessionStorage.getItem(STORAGE_KEYS.OAUTH_STATE);
    const verifier = sessionStorage.getItem(STORAGE_KEYS.CODE_VERIFIER);

    if (!savedState || savedState !== state) {
      throw new Error('OAuth state mismatch. Potential CSRF attack detected.');
    }

    if (!verifier) {
      throw new Error('PKCE code verifier not found in session.');
    }

    const redirectUri = `${window.location.origin}/auth/callback`;

    const params = new URLSearchParams();
    params.append('grant_type', 'authorization_code');
    params.append('client_id', KEYCLOAK_CONFIG.clientId);
    params.append('code', code);
    params.append('code_verifier', verifier);
    params.append('redirect_uri', redirectUri);

    const tokenUrl = `${KEYCLOAK_CONFIG.url}/realms/${KEYCLOAK_CONFIG.realm}/protocol/openid-connect/token`;

    const response = await fetch(tokenUrl, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
      },
      body: params.toString(),
    });

    if (!response.ok) {
      const err = await response.json().catch(() => ({}));
      throw new Error(err.error_description || err.error || 'Failed to exchange authorization code for tokens.');
    }

    const data = await response.json();

    // Store tokens securely
    sessionStorage.setItem(STORAGE_KEYS.ACCESS_TOKEN, data.access_token);
    if (data.refresh_token) {
      sessionStorage.setItem(STORAGE_KEYS.REFRESH_TOKEN, data.refresh_token);
    }
    if (data.id_token) {
      sessionStorage.setItem(STORAGE_KEYS.ID_TOKEN, data.id_token);
    }

    // Clean up PKCE verifier and state
    sessionStorage.removeItem(STORAGE_KEYS.CODE_VERIFIER);
    sessionStorage.removeItem(STORAGE_KEYS.OAUTH_STATE);

    return this.getUserProfile();
  }

  /**
   * Exchange refresh token for a fresh access token.
   *
   * @returns {Promise<string|null>} New access token or null
   */
  async refreshToken() {
    const refreshToken = sessionStorage.getItem(STORAGE_KEYS.REFRESH_TOKEN);
    if (!refreshToken) return null;

    const params = new URLSearchParams();
    params.append('grant_type', 'refresh_token');
    params.append('client_id', KEYCLOAK_CONFIG.clientId);
    params.append('refresh_token', refreshToken);

    const tokenUrl = `${KEYCLOAK_CONFIG.url}/realms/${KEYCLOAK_CONFIG.realm}/protocol/openid-connect/token`;

    try {
      const response = await fetch(tokenUrl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: params.toString(),
      });

      if (!response.ok) {
        this.clearSession();
        return null;
      }

      const data = await response.json();
      sessionStorage.setItem(STORAGE_KEYS.ACCESS_TOKEN, data.access_token);
      if (data.refresh_token) {
        sessionStorage.setItem(STORAGE_KEYS.REFRESH_TOKEN, data.refresh_token);
      }
      return data.access_token;
    } catch {
      this.clearSession();
      return null;
    }
  }

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
        administrativeScope: {
          tier_level: decoded.tier_level || decoded.administrative_profile?.tier_level || 'NATIONAL',
          name_en: decoded.name || 'Jurisdiction',
          name_bn: decoded.designation || 'অধিক্ষেত্র',
          ancestry_path: decoded.ancestry_path || decoded.administrative_profile?.ancestry_path || '/',
          scope_id: decoded.scope_id || decoded.administrative_profile?.scope_id,
        },
      };
    } catch {
      return null;
    }
  }

  /**
   * Local Dev/Mock Login simulator for development testing.
   */
  devLogin(role = 'ROLE_DRRO', tier = 'DISTRICT') {
    const mockPayload = {
      sub: '4a7f29b0-8c21-41e9-b541-6e3e57f20108',
      preferred_username: 'drro_khulna',
      name: 'Md. Rafiqul Islam (DRRO)',
      email: 'drro.khulna@modmr.gov.bd',
      roles: [role],
      exp: Math.floor(Date.now() / 1000) + 3600 * 8,
      nbf: Math.floor(Date.now() / 1000),
      iat: Math.floor(Date.now() / 1000),
      iss: `${KEYCLOAK_CONFIG.url}/realms/${KEYCLOAK_CONFIG.realm}`,
      aud: 'idms-backend-api',
      tier_level: tier,
      ancestry_path: tier === 'NATIONAL' ? '/' : '/40/47/',
      designation: 'District Relief and Rehabilitation Officer',
    };

    const header = btoa(JSON.stringify({ alg: 'RS256', typ: 'JWT', kid: 'dev-key' }));
    const payload = btoa(JSON.stringify(mockPayload));
    const signature = btoa('mock-dev-signature-modmr-keycloak');
    const mockToken = `${header}.${payload}.${signature}`;

    sessionStorage.setItem(STORAGE_KEYS.ACCESS_TOKEN, mockToken);
    return this.getUserProfile();
  }

  /**
   * Clear session tokens without redirecting.
   */
  clearSession() {
    sessionStorage.removeItem(STORAGE_KEYS.ACCESS_TOKEN);
    sessionStorage.removeItem(STORAGE_KEYS.REFRESH_TOKEN);
    sessionStorage.removeItem(STORAGE_KEYS.ID_TOKEN);
    sessionStorage.removeItem(STORAGE_KEYS.USER_DATA);
  }

  /**
   * Secure OIDC Logout redirecting to Keycloak end-session endpoint.
   */
  logout() {
    const idToken = sessionStorage.getItem(STORAGE_KEYS.ID_TOKEN);
    this.clearSession();

    const postLogoutRedirect = encodeURIComponent(`${window.location.origin}/login`);
    let logoutUrl = `${KEYCLOAK_CONFIG.url}/realms/${KEYCLOAK_CONFIG.realm}/protocol/openid-connect/logout?client_id=${KEYCLOAK_CONFIG.clientId}&post_logout_redirect_uri=${postLogoutRedirect}`;

    if (idToken) {
      logoutUrl += `&id_token_hint=${encodeURIComponent(idToken)}`;
    }

    window.location.href = logoutUrl;
  }
}

export const keycloakService = new KeycloakService();

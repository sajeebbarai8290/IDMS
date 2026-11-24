import React, { createContext, useState, useEffect, useCallback } from 'react';
import { keycloakService } from '../api/keycloakService';

export const AuthContext = createContext(null);

export const AuthProvider = ({ children }) => {
  const [user, setUser] = useState(null);
  const [isAuthenticated, setIsAuthenticated] = useState(false);
  const [isLoading, setIsLoading] = useState(true);

  // Initialize auth state on mount
  useEffect(() => {
    if (keycloakService.isAuthenticated()) {
      const profile = keycloakService.getUserProfile();
      setUser(profile);
      setIsAuthenticated(true);
    } else {
      setUser(null);
      setIsAuthenticated(false);
    }
    setIsLoading(false);
  }, []);

  const login = useCallback(() => {
    keycloakService.login();
  }, []);

  const devLogin = useCallback((role = 'ROLE_DRRO', tier = 'DISTRICT') => {
    const profile = keycloakService.devLogin(role, tier);
    setUser(profile);
    setIsAuthenticated(true);
  }, []);

  const logout = useCallback(() => {
    keycloakService.logout();
    setUser(null);
    setIsAuthenticated(false);
  }, []);

  const hasRole = useCallback((role) => {
    if (!user || !user.roles) return false;
    return user.roles.includes(role);
  }, [user]);

  const value = {
    user,
    isAuthenticated,
    isLoading,
    login,
    devLogin,
    logout,
    hasRole,
    administrativeScope: user?.administrativeScope || null,
  };

  return (
    <AuthContext.Provider value={value}>
      {children}
    </AuthContext.Provider>
  );
};

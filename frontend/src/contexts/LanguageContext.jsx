import React, { createContext, useContext, useState, useCallback } from 'react';

const LanguageContext = createContext({
  lang: 'bn',
  setLang: () => {},
  t: (bn) => bn,
});

export const LanguageProvider = ({ children }) => {
  const [lang, setLangState] = useState(() => {
    try {
      return localStorage.getItem('idms_lang') || 'bn';
    } catch {
      return 'bn';
    }
  });

  const setLang = useCallback((nextLang) => {
    const normalized = nextLang === 'en' ? 'en' : 'bn';
    setLangState(normalized);
    try {
      localStorage.setItem('idms_lang', normalized);
    } catch {
      // ignore storage errors
    }
  }, []);

  const t = useCallback(
    (bn, en) => (lang === 'en' ? en || bn : bn),
    [lang]
  );

  return (
    <LanguageContext.Provider value={{ lang, setLang, t }}>
      {children}
    </LanguageContext.Provider>
  );
};

export const useLanguage = () => useContext(LanguageContext);

export default LanguageContext;

import React, { useState } from 'react';
import Header from './Header';
import Sidebar from './Sidebar';
import Footer from './Footer';

export const MainLayout = ({ children }) => {
  const [sidebarOpen, setSidebarOpen] = useState(false);

  const toggleSidebar = () => {
    setSidebarOpen(!sidebarOpen);
  };

  return (
    <div className="idms-app-container">
      <Header toggleSidebar={toggleSidebar} />
      <div className="d-flex">
        <Sidebar isOpen={sidebarOpen} />
        <main className="idms-main-wrapper flex-grow-1">
          <div className="idms-content-area">
            {children}
          </div>
          <Footer />
        </main>
      </div>
    </div>
  );
};

export default MainLayout;

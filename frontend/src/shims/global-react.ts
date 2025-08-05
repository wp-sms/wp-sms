// Global React shim for our own React installation
import * as React from 'react';
import * as ReactDOM from 'react-dom';

// Make React available globally
declare global {
  interface Window {
    React: typeof React;
    ReactDOM: typeof ReactDOM;
  }
}

// Export React and ReactDOM
export * from 'react';
export * from 'react-dom';
export default React; 
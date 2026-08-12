import './bootstrap';
import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { BrowserRouter } from 'react-router-dom';
import { PlatformApp } from './PlatformApp';
import { I18nProvider } from './packages/i18n/I18nProvider';

const root = document.getElementById('app');

if (!root) {
    throw new Error('Application root was not found.');
}

createRoot(root).render(
    <StrictMode>
        <BrowserRouter>
            <I18nProvider>
                <PlatformApp />
            </I18nProvider>
        </BrowserRouter>
    </StrictMode>,
);

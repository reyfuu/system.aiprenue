import React from 'react';
import { createRoot } from 'react-dom/client';
import Pembukuan from './components/Pembukuan.jsx';

const el = document.getElementById('pembukuan-root');
if (el) {
    const payload = JSON.parse(el.dataset.payload || '{}');
    createRoot(el).render(<Pembukuan data={payload} />);
}

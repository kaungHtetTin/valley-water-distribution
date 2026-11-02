import { useEffect, useState } from 'react';
import { createPortal } from 'react-dom';
import { X } from 'lucide-react';

let showRequest;
let activeRequest = null;
const queue = [];
function ask(options) {
    return new Promise((resolve) => {
        queue.push({ ...options, resolve });
        if (!activeRequest) activeRequest = queue.shift();
        showRequest?.();
    });
}
export const confirmAction = (message, options = {}) => ask({ message, ...options });
export const requestText = (message, value = '') => ask({ message, value, input: true });

export function ConfirmationHost() {
    const [request, setRequest] = useState(null);
    const [value, setValue] = useState('');
    useEffect(() => {
        showRequest = () => setRequest(activeRequest);
        showRequest();
        return () => { showRequest = undefined; };
    }, []);
    useEffect(() => { setValue(request?.value || ''); }, [request]);
    if (!request) return null;
    const my = document.querySelector('.app-root')?.dataset.locale === 'my';
    const close = (result) => {
        if (request !== activeRequest) return;
        activeRequest = queue.shift() || null;
        request.resolve(result);
        setValue(activeRequest?.value || '');
        setRequest(activeRequest);
    };
    return createPortal(<div className="modal-backdrop confirmation-backdrop">
        <form className="master-dialog confirmation-dialog" role="dialog" aria-modal="true" aria-labelledby="confirmation-title" aria-describedby="confirmation-message" onSubmit={(event) => { event.preventDefault(); close(request.input ? value : true); }}>
            <header><h2 id="confirmation-title">{my ? 'အတည်ပြုရန်' : 'Confirm action'}</h2><button className="icon-button" type="button" aria-label={my ? 'ပိတ်ရန်' : 'Close'} onClick={() => close(request.input ? null : false)}><X size={16} /></button></header>
            <div className="confirmation-body"><p id="confirmation-message">{request.message}</p>{request.input && <label className="master-field">{request.message}<input autoFocus value={value} onChange={(event) => setValue(event.target.value)} /></label>}</div>
            <footer><button autoFocus={!request.input} className="button" type="button" onClick={() => close(request.input ? null : false)}>{my ? 'မလုပ်တော့ပါ' : 'Cancel'}</button><button className={`button ${request.danger ? 'danger' : 'primary'}`} type="submit">{request.confirmLabel || (my ? 'အတည်ပြုမည်' : 'Confirm')}</button></footer>
        </form>
    </div>, document.querySelector('.app-root') || document.body);
}

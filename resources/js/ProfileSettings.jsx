import { Camera, Crop, KeyRound, Save, ShieldCheck, UserRound, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

const authRoute = (action, fallback) => window.ValleyRuntime?.auth?.[action] || fallback;
const PROFILE_PHOTO_SOURCE_MAX = 2 * 1024 * 1024;
const PROFILE_PHOTO_TARGET_MAX = 500 * 1024;
const PROFILE_PHOTO_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

const canvasBlob = (canvas, type, quality) => new Promise((resolve) => canvas.toBlob(resolve, type, quality));
const clamp = (value, minimum, maximum) => Math.min(maximum, Math.max(minimum, value));

function pointerMetrics(pointers) {
    const points = [...pointers.values()];
    if (!points.length) return { center: null, distance: null };
    if (points.length === 1) return { center: points[0], distance: null };
    const [first, second] = points;
    return {
        center: { x: (first.x + second.x) / 2, y: (first.y + second.y) / 2 },
        distance: Math.hypot(second.x - first.x, second.y - first.y),
    };
}

function drawSquareCrop(canvas, image, crop) {
    const size = canvas.width;
    const baseScale = Math.max(size / image.naturalWidth, size / image.naturalHeight);
    const scale = baseScale * crop.zoom;
    const width = image.naturalWidth * scale;
    const height = image.naturalHeight * scale;
    const x = -(width - size) * (crop.x / 100);
    const y = -(height - size) * (crop.y / 100);
    const context = canvas.getContext('2d');
    context.clearRect(0, 0, size, size);
    context.drawImage(image, x, y, width, height);
}

async function compressProfilePhoto(file) {
    if (!PROFILE_PHOTO_TYPES.includes(file.type)) throw new Error('Choose a JPG, PNG or WebP image.');
    if (file.size > PROFILE_PHOTO_SOURCE_MAX) throw new Error('The original image must not exceed 2 MB.');
    if (file.size <= PROFILE_PHOTO_TARGET_MAX) return file;

    const objectUrl = URL.createObjectURL(file);
    try {
        const image = await new Promise((resolve, reject) => {
            const element = new Image();
            element.onload = () => resolve(element);
            element.onerror = () => reject(new Error('The selected image could not be processed.'));
            element.src = objectUrl;
        });
        const initialScale = Math.min(1, 1600 / Math.max(image.naturalWidth, image.naturalHeight));
        let width = Math.max(1, Math.round(image.naturalWidth * initialScale));
        let height = Math.max(1, Math.round(image.naturalHeight * initialScale));
        let quality = 0.84;
        let blob = null;

        for (let attempt = 0; attempt < 10; attempt += 1) {
            const canvas = document.createElement('canvas');
            canvas.width = width;
            canvas.height = height;
            const context = canvas.getContext('2d');
            context.drawImage(image, 0, 0, width, height);
            blob = await canvasBlob(canvas, 'image/webp', quality);
            if (blob && blob.size <= PROFILE_PHOTO_TARGET_MAX) break;

            if (quality > 0.48) quality -= 0.1;
            else {
                width = Math.max(1, Math.round(width * 0.82));
                height = Math.max(1, Math.round(height * 0.82));
                quality = 0.72;
            }
        }

        if (!blob || blob.size > PROFILE_PHOTO_TARGET_MAX) throw new Error('The image could not be compressed below 500 KB. Try a smaller image.');
        const extension = blob.type === 'image/webp' ? 'webp' : blob.type === 'image/png' ? 'png' : 'jpg';
        const baseName = file.name.replace(/\.[^.]+$/, '') || 'profile-photo';
        return new File([blob], `${baseName}.${extension}`, { type: blob.type, lastModified: Date.now() });
    } finally {
        URL.revokeObjectURL(objectUrl);
    }
}

function Field({ label, error, children }) {
    return (
        <label className="master-field">
            <span>{label}</span>
            {children}
            {error && <small>{error}</small>}
        </label>
    );
}

export function ProfileSettingsScreen({ user, onUserUpdated }) {
    const [profile, setProfile] = useState({ name: user.name || '', email: user.email || '', phone: user.phone || '' });
    const [profilePhoto, setProfilePhoto] = useState(null);
    const [photoPreview, setPhotoPreview] = useState(user.profile_photo_url || '');
    const [photoProcessing, setPhotoProcessing] = useState(false);
    const [cropper, setCropper] = useState(null);
    const [security, setSecurity] = useState({ current_password: '', password: '', password_confirmation: '' });
    const [profileState, setProfileState] = useState({ saving: false, errors: {}, message: '' });
    const [securityState, setSecurityState] = useState({ saving: false, errors: {}, message: '' });
    const photoInputRef = useRef(null);
    const cropCanvasRef = useRef(null);
    const cropGestureRef = useRef({ pointers: new Map(), center: null, distance: null });

    useEffect(() => {
        if (cropper?.image && cropCanvasRef.current) drawSquareCrop(cropCanvasRef.current, cropper.image, cropper);
    }, [cropper]);

    const closeCropper = () => {
        if (cropper?.url) URL.revokeObjectURL(cropper.url);
        cropGestureRef.current = { pointers: new Map(), center: null, distance: null };
        setCropper(null);
        if (photoInputRef.current) photoInputRef.current.value = '';
    };

    const selectProfilePhoto = async (event) => {
        const source = event.target.files?.[0] || null;
        if (!source) return;
        let selectedUrl = '';
        setProfileState((current) => ({ ...current, errors: { ...current.errors, profile_photo: undefined }, message: '' }));

        try {
            if (!PROFILE_PHOTO_TYPES.includes(source.type)) throw new Error('Choose a JPG, PNG or WebP image.');
            if (source.size > PROFILE_PHOTO_SOURCE_MAX) throw new Error('The original image must not exceed 2 MB.');
            selectedUrl = URL.createObjectURL(source);
            const image = await new Promise((resolve, reject) => {
                const element = new Image();
                element.onload = () => resolve(element);
                element.onerror = () => reject(new Error('The selected image could not be opened.'));
                element.src = selectedUrl;
            });
            setCropper({ file: source, url: selectedUrl, image, zoom: 1, x: 50, y: 50 });
        } catch (error) {
            if (selectedUrl) URL.revokeObjectURL(selectedUrl);
            setProfileState((current) => ({ ...current, errors: { ...current.errors, profile_photo: [error.message] }, message: '' }));
            if (photoInputRef.current) photoInputRef.current.value = '';
        }
    };

    const applyCrop = async () => {
        if (!cropper?.image) return;
        setPhotoProcessing(true);
        try {
            const canvas = document.createElement('canvas');
            canvas.width = 1024;
            canvas.height = 1024;
            drawSquareCrop(canvas, cropper.image, cropper);
            const blob = await canvasBlob(canvas, 'image/webp', 0.88);
            if (!blob) throw new Error('The cropped image could not be created.');
            const baseName = cropper.file.name.replace(/\.[^.]+$/, '') || 'profile-photo';
            const cropped = new File([blob], `${baseName}-square.webp`, { type: blob.type, lastModified: Date.now() });
            const compressed = await compressProfilePhoto(cropped);
            setProfilePhoto(compressed);
            const reader = new FileReader();
            reader.onload = () => setPhotoPreview(String(reader.result || ''));
            reader.readAsDataURL(compressed);
            closeCropper();
        } catch (error) {
            setProfileState((current) => ({ ...current, errors: { ...current.errors, profile_photo: [error.message] }, message: '' }));
        } finally {
            setPhotoProcessing(false);
        }
    };

    const updateCropGesture = (deltaX, deltaY, zoomRatio = 1) => {
        const canvas = cropCanvasRef.current;
        if (!canvas || !cropper?.image) return;
        const rect = canvas.getBoundingClientRect();
        setCropper((current) => {
            if (!current) return current;
            const zoom = clamp(current.zoom * zoomRatio, 1, 3);
            const baseScale = Math.max(rect.width / current.image.naturalWidth, rect.height / current.image.naturalHeight);
            const overflowX = Math.max((current.image.naturalWidth * baseScale * zoom) - rect.width, 0);
            const overflowY = Math.max((current.image.naturalHeight * baseScale * zoom) - rect.height, 0);
            return {
                ...current,
                zoom,
                x: overflowX ? clamp(current.x - (deltaX / overflowX) * 100, 0, 100) : 50,
                y: overflowY ? clamp(current.y - (deltaY / overflowY) * 100, 0, 100) : 50,
            };
        });
    };

    const beginCropGesture = (event) => {
        event.currentTarget.setPointerCapture(event.pointerId);
        const gesture = cropGestureRef.current;
        gesture.pointers.set(event.pointerId, { x: event.clientX, y: event.clientY });
        Object.assign(gesture, pointerMetrics(gesture.pointers));
    };

    const moveCropGesture = (event) => {
        const gesture = cropGestureRef.current;
        if (!gesture.pointers.has(event.pointerId)) return;
        const previousCenter = gesture.center;
        const previousDistance = gesture.distance;
        gesture.pointers.set(event.pointerId, { x: event.clientX, y: event.clientY });
        const next = pointerMetrics(gesture.pointers);
        const deltaX = previousCenter && next.center ? next.center.x - previousCenter.x : 0;
        const deltaY = previousCenter && next.center ? next.center.y - previousCenter.y : 0;
        const zoomRatio = previousDistance && next.distance ? next.distance / previousDistance : 1;
        updateCropGesture(deltaX, deltaY, zoomRatio);
        Object.assign(gesture, next);
    };

    const endCropGesture = (event) => {
        const gesture = cropGestureRef.current;
        gesture.pointers.delete(event.pointerId);
        Object.assign(gesture, pointerMetrics(gesture.pointers));
        if (event.currentTarget.hasPointerCapture(event.pointerId)) event.currentTarget.releasePointerCapture(event.pointerId);
    };

    const updateProfile = async (event) => {
        event.preventDefault();
        setProfileState({ saving: true, errors: {}, message: '' });

        try {
            const payload = new FormData();
            payload.append('_method', 'PUT');
            payload.append('name', profile.name);
            payload.append('email', profile.email);
            payload.append('phone', profile.phone || '');
            if (profilePhoto) payload.append('profile_photo', profilePhoto);
            const response = await window.axios.post(authRoute('profile', '/api/auth/profile'), payload);
            onUserUpdated(response.data.data.user);
            setPhotoPreview(response.data.data.user.profile_photo_url || '');
            setProfilePhoto(null);
            if (photoInputRef.current) photoInputRef.current.value = '';
            setProfileState({ saving: false, errors: {}, message: response.data.message });
        } catch (error) {
            setProfileState({
                saving: false,
                errors: error.response?.data?.errors || {},
                message: error.response?.data?.message || 'Unable to update profile information.',
            });
        }
    };

    const updatePassword = async (event) => {
        event.preventDefault();
        setSecurityState({ saving: true, errors: {}, message: '' });

        try {
            const response = await window.axios.put(authRoute('password', '/api/auth/password'), security);
            setSecurity({ current_password: '', password: '', password_confirmation: '' });
            setSecurityState({ saving: false, errors: {}, message: response.data.message });
        } catch (error) {
            setSecurityState({
                saving: false,
                errors: error.response?.data?.errors || {},
                message: error.response?.data?.message || 'Unable to update password.',
            });
        }
    };

    return (
        <section className="page profile-settings-page">
            <div className="master-heading">
                <div>
                    <p className="eyebrow">Account settings</p>
                    <h1>Profile & security</h1>
                    <span className="muted">Manage your personal information and sign-in password.</span>
                </div>
            </div>

            <div className="profile-settings-grid">
                <form className="master-panel profile-settings-card" onSubmit={updateProfile}>
                    <header className="profile-settings-card-heading">
                        <span className="profile-settings-icon"><UserRound size={17} /></span>
                        <div><h2>Profile information</h2><p>Update the details shown across the application.</p></div>
                    </header>
                    <div className="profile-settings-form">
                        <div className="profile-photo-field">
                            <span className="profile-photo-preview" aria-hidden="true">{photoPreview ? <img src={photoPreview} alt="" /> : user.name?.split(/\s+/).map((part) => part[0]).join('').slice(0, 2).toUpperCase()}</span>
                            <div><strong>Profile photo</strong><small>Upload up to 2 MB. Crop to square and store at 500 KB or less.</small><label className={`button ${photoProcessing ? 'is-disabled' : ''}`} htmlFor="account-profile-photo" aria-disabled={photoProcessing}><Camera size={15} />{photoProcessing ? 'Processing…' : profilePhoto ? 'Change photo' : 'Choose photo'}</label><input ref={photoInputRef} id="account-profile-photo" className="profile-photo-input" type="file" accept="image/jpeg,image/png,image/webp" disabled={photoProcessing} onChange={selectProfilePhoto} />{profilePhoto && <small className="profile-photo-name">{profilePhoto.name} · {Math.ceil(profilePhoto.size / 1024)} KB</small>}{profileState.errors.profile_photo?.[0] && <small className="profile-photo-error">{profileState.errors.profile_photo[0]}</small>}</div>
                        </div>
                        <Field label="Full name" error={profileState.errors.name?.[0]}>
                            <input required autoComplete="name" value={profile.name} onChange={(event) => setProfile((current) => ({ ...current, name: event.target.value }))} />
                        </Field>
                        <Field label="Email address" error={profileState.errors.email?.[0]}>
                            <input required type="email" autoComplete="email" value={profile.email} onChange={(event) => setProfile((current) => ({ ...current, email: event.target.value }))} />
                        </Field>
                        <Field label="Phone number" error={profileState.errors.phone?.[0]}>
                            <input type="tel" autoComplete="tel" placeholder="Optional" value={profile.phone} onChange={(event) => setProfile((current) => ({ ...current, phone: event.target.value }))} />
                        </Field>
                        <Field label="Role">
                            <input value={user.role} disabled />
                        </Field>
                    </div>
                    <footer>
                        {profileState.message && <span className={Object.keys(profileState.errors).length ? 'settings-message error' : 'settings-message success'}>{profileState.message}</span>}
                        <button className="button primary" disabled={profileState.saving || photoProcessing}>
                            <Save size={15} />{profileState.saving ? 'Saving…' : 'Save profile'}
                        </button>
                    </footer>
                </form>

                <form className="master-panel profile-settings-card" onSubmit={updatePassword}>
                    <header className="profile-settings-card-heading">
                        <span className="profile-settings-icon"><ShieldCheck size={17} /></span>
                        <div><h2>Security</h2><p>Use a strong password with at least 8 characters.</p></div>
                    </header>
                    <div className="profile-settings-form single-column">
                        <Field label="Current password" error={securityState.errors.current_password?.[0]}>
                            <input required type="password" autoComplete="current-password" value={security.current_password} onChange={(event) => setSecurity((current) => ({ ...current, current_password: event.target.value }))} />
                        </Field>
                        <Field label="New password" error={securityState.errors.password?.[0]}>
                            <input required minLength="8" type="password" autoComplete="new-password" value={security.password} onChange={(event) => setSecurity((current) => ({ ...current, password: event.target.value }))} />
                        </Field>
                        <Field label="Confirm new password" error={securityState.errors.password_confirmation?.[0]}>
                            <input required minLength="8" type="password" autoComplete="new-password" value={security.password_confirmation} onChange={(event) => setSecurity((current) => ({ ...current, password_confirmation: event.target.value }))} />
                        </Field>
                    </div>
                    <footer>
                        {securityState.message && <span className={Object.keys(securityState.errors).length ? 'settings-message error' : 'settings-message success'}>{securityState.message}</span>}
                        <button className="button primary" disabled={securityState.saving}>
                            <KeyRound size={15} />{securityState.saving ? 'Updating…' : 'Update password'}
                        </button>
                    </footer>
                </form>
            </div>

            {cropper && <div className="modal-backdrop" role="presentation" onMouseDown={(event) => event.target === event.currentTarget && closeCropper()}>
                <section className="master-dialog profile-crop-dialog" role="dialog" aria-modal="true" aria-labelledby="profile-crop-title" onKeyDown={(event) => event.key === 'Escape' && closeCropper()}>
                    <header><div><p className="eyebrow">Profile photo</p><h2 id="profile-crop-title">Crop square image</h2></div><button className="icon-button" type="button" aria-label="Close cropper" onClick={closeCropper}><X size={17} /></button></header>
                    <div className="profile-crop-body">
                        <div className="profile-crop-stage"><canvas ref={cropCanvasRef} className="profile-crop-canvas" width="600" height="600" role="img" tabIndex="0" aria-label="Square profile photo crop preview. Drag to reposition and pinch to zoom." onPointerDown={beginCropGesture} onPointerMove={moveCropGesture} onPointerUp={endCropGesture} onPointerCancel={endCropGesture} onWheel={(event) => { event.preventDefault(); updateCropGesture(0, 0, event.deltaY < 0 ? 1.08 : 0.92); }} onKeyDown={(event) => {
                            const actions = { ArrowLeft: [8, 0, 1], ArrowRight: [-8, 0, 1], ArrowUp: [0, 8, 1], ArrowDown: [0, -8, 1], '+': [0, 0, 1.08], '=': [0, 0, 1.08], '-': [0, 0, 0.92] };
                            if (actions[event.key]) {
                                event.preventDefault();
                                updateCropGesture(...actions[event.key]);
                            }
                        }} /><span>{cropper.zoom.toFixed(1)}×</span></div>
                        <p className="profile-crop-hint">Drag with one finger to reposition. Pinch with two fingers to zoom in or out.</p>
                    </div>
                    <footer><button className="button" type="button" onClick={closeCropper}>Cancel</button><button className="button" type="button" onClick={() => setCropper((current) => ({ ...current, zoom: 1, x: 50, y: 50 }))}>Reset</button><button className="button primary" type="button" disabled={photoProcessing} onClick={applyCrop}><Crop size={15} />{photoProcessing ? 'Processing…' : 'Apply crop'}</button></footer>
                </section>
            </div>}
        </section>
    );
}

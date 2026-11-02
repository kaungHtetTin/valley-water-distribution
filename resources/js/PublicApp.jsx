import { useEffect, useState } from 'react';
import { ArrowRight, Droplets, Home, Package, Search, UserRound } from 'lucide-react';

export const publicText = (locale, en, my) => locale === 'my' ? my : en;

export function ProductCatalog({ locale = 'en', onOrder, embedded = false }) {
    const [state, setState] = useState({ loading: true, products: [], error: '' });
    const [search, setSearch] = useState('');
    const [revision, setRevision] = useState(0);
    const t = (en, my) => publicText(locale, en, my);
    const Heading = embedded ? 'h2' : 'h1';
    useEffect(() => {
        const controller = new AbortController();
        setState((current) => ({ ...current, loading: true, error: '' }));
        window.axios.get(window.ValleyRuntime?.api?.publicProducts || '/api/public/products', { signal: controller.signal })
            .then(({ data }) => setState({ loading: false, products: data.data.products, error: '' }))
            .catch((error) => { if (error.code !== 'ERR_CANCELED') setState({ loading: false, products: [], error: 'unavailable' }); });
        return () => controller.abort();
    }, [revision]);
    const products = state.products.filter((item) => `${item.name} ${item.sku}`.toLocaleLowerCase().includes(search.toLocaleLowerCase()));
    return <section className="public-products" aria-busy={state.loading}>
        <header className="mobile-section-heading"><div><p className="eyebrow">VALLEY WATER</p><Heading>{t('Our water', 'ကျွန်ုပ်တို့၏ ရေသန့်များ')}</Heading><p className="muted">{t('Choose your water. Sign in to see your price and arrange delivery.', 'ရေသန့်ရွေးပြီး သင့်စျေးနှုန်းနှင့် ပို့ဆောင်မှုအတွက် အကောင့်ဝင်ပါ။')}</p></div></header>
        <label className="mobile-search public-product-search"><Search size={18} /><input type="search" aria-label={t('Search products', 'ပစ္စည်းရှာရန်')} placeholder={t('Search water products', 'ရေသန့်ပစ္စည်း ရှာရန်')} value={search} onChange={(event) => setSearch(event.target.value)} /></label>
        {state.loading ? <p role="status">{t('Loading products…', 'ပစ္စည်းများ ရယူနေသည်…')}</p> : state.error ? <div role="alert"><p>{t('Products could not be loaded.', 'ပစ္စည်းများ ရယူ၍မရပါ။')}</p><button className="button" onClick={() => setRevision((value) => value + 1)}>{t('Try again', 'ပြန်ကြိုးစားရန်')}</button></div> : <div className="public-product-grid">{products.map((product) => <article className="public-product-card" key={product.id}>
            <span className="public-product-icon"><Droplets size={30} /></span><h2>{product.name}</h2><p className="muted">{product.unit} · {product.sku}</p><span className={`status ${product.in_stock ? 'success' : 'neutral'}`}>{product.in_stock ? t('Available to order', 'မှာယူနိုင်သည်') : t('Currently unavailable', 'လောလောဆယ် မရနိုင်ပါ')}</span>
            <button className="button primary" type="button" disabled={!product.in_stock} onClick={() => onOrder(product.id)}>{t('Order this water', 'ဤရေသန့်ကို မှာယူရန်')}<ArrowRight size={16} /></button>
        </article>)}</div>}
        {!state.loading && !state.error && !products.length && <p role="status">{search ? t('No products match your search.', 'ရှာဖွေမှုနှင့် ကိုက်ညီသောပစ္စည်း မရှိပါ။') : t('Products will appear here when available.', 'ရရှိနိုင်သောပစ္စည်းများကို ဤနေရာတွင် ပြပါမည်။')}</p>}
    </section>;
}

export function PublicWelcome({ locale, theme, setLocale, setTheme, navigate, path }) {
    const products = window.location.pathname.endsWith('/products');
    const t = (en, my) => publicText(locale, en, my);
    const signIn = (productId) => {
        try {
            if (productId) window.sessionStorage.setItem('valley:public-product', String(productId));
            else window.sessionStorage.removeItem('valley:public-product');
        } catch { /* The URL still preserves the selection for password sign-in. */ }
        navigate(`${path}/sign-in${productId ? `?product=${productId}` : ''}`);
    };
    return <main className="mobile-app-shell public-app-shell">
        <header className="public-topbar"><a href={path} onClick={(event) => { event.preventDefault(); navigate(path); }}><Droplets size={24} /><strong>Valley Water</strong></a><button className="button" onClick={() => setLocale(locale === 'en' ? 'my' : 'en')}>{locale === 'en' ? 'မြန်မာ' : 'English'}</button><button className="button" onClick={() => setTheme(theme === 'light' ? 'dark' : 'light')}>{t(theme === 'light' ? 'Dark' : 'Light', theme === 'light' ? 'အမှောင်' : 'အလင်း')}</button></header>
        <section className="mobile-page">
            {!products && <section className="public-welcome-hero"><Droplets size={36} /><p className="eyebrow">VALLEY WATER</p><h1>{t('Water for your everyday.', 'နေ့စဉ်အတွက် ရေသန့်။')}</h1><p>{t('Order water for your home or business.', 'အိမ်နှင့် လုပ်ငန်းအတွက် ရေသန့်မှာယူပါ။')}</p><button className="button primary" onClick={() => navigate(`${path}/products`)}>{t('Order water', 'ရေသန့်မှာယူရန်')}<ArrowRight size={17} /></button><button className="button" onClick={() => signIn()}>{t('Track my order', 'ကျွန်ုပ်၏အော်ဒါ ကြည့်ရန်')}</button><p className="muted">{t('Browse freely. Sign in when you are ready to order.', 'ပစ္စည်းများကို လွတ်လပ်စွာကြည့်ပြီး မှာယူသည့်အခါ အကောင့်ဝင်ပါ။')}</p></section>}
            <ProductCatalog locale={locale} onOrder={signIn} embedded={!products} />
        </section>
        <nav className="bottom-nav public-bottom-nav" aria-label={t('Main navigation', 'အဓိက လမ်းညွှန်')} style={{ '--mobile-nav-count': 4 }}>{[[Home, t('Home', 'ပင်မ'), path], [Droplets, t('Products', 'ပစ္စည်းများ'), `${path}/products`], [Package, t('My orders', 'ကျွန်ုပ်၏အော်ဒါများ'), `${path}/sign-in`], [UserRound, t('Account', 'အကောင့်'), `${path}/sign-in`]].map(([Icon, label, href], index) => <a key={index} className={(products ? index === 1 : index === 0) ? 'is-active' : ''} href={href} onClick={(event) => { event.preventDefault(); navigate(href); }}><Icon size={18} /><span>{label}</span></a>)}</nav>
    </main>;
}

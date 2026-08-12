# Platform foundation architecture

## Runtime shape

The initial system is an API-first Laravel modular monolith. It owns transactional consistency while React applications consume versioned JSON endpoints. The applications share design tokens, domain types, localization, API transport, authentication, and offline primitives.

```text
Laravel
  app/Domain/                 bounded business modules
  app/Http/                   transport and middleware only
  app/Support/Tenancy/        request organization context
  routes/api.php              /api/v1 contract

React
  resources/js/apps/office/   desktop operations shell
  resources/js/apps/sales/    field-sales shell
  resources/js/apps/driver/   delivery shell
  resources/js/apps/client/   reseller shell
  resources/js/packages/      shared API, types, i18n, and design system
```

## Initial boundaries

- Browser applications are same-origin and use Laravel CSRF/session foundations.
- Every response receives a correlation ID.
- Every business table added after Phase 0 must carry an organization boundary where applicable.
- Business timestamps are stored in UTC; business periods render in Asia/Yangon.
- Incomplete transaction paths remain disabled through configuration flags.
- Controllers delegate to module application services; controllers do not query across module boundaries.

## Next architecture work

- authentication and trusted-device threat model;
- permission, data-scope, and approval policy schema;
- OpenAPI generation and typed client generation;
- idempotency record and transactional outbox schemas;
- MySQL, Redis, object storage, queue, and live-update environment topology;
- offline command envelope and conflict contract.

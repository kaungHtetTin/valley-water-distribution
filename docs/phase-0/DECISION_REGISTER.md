# Phase 0 decision register

All items are currently `OPEN` and require a named business owner plus approval evidence before dependent transaction work is activated.

| ID | Decision | Recommended baseline | Owner | Status |
| --- | --- | --- | --- | --- |
| P0-001 | Product sizes and packaging | Dynamic 0.5 L, 0.7 L, 1 L with verified conversions | TBD | OPEN |
| P0-002 | Inventory cost valuation | Weighted average per warehouse/SKU | Finance | OPEN |
| P0-003 | Invoice, tax, and credit-note timing | Pro forma at confirmation; official invoice at accepted delivery | Finance | OPEN |
| P0-004 | Credit policy | COD/zero limit by default; explicit approved limit and terms | Credit Control | OPEN |
| P0-005 | Partial and split delivery | Partial allowed; multi-warehouse split by Office approval | Operations | OPEN |
| P0-006 | POD evidence | Recipient, GPS, and time; risk-based photo/signature/OTP | Operations | OPEN |
| P0-007 | Driver packaging and GPS | Managed Android hybrid shell with background location | Operations/IT | OPEN |
| P0-008 | GPS retention | 7-day hot raw, up to 90-day archive, seven-year trip summary | Legal/Operations | OPEN |
| P0-009 | OTP and assisted fallback | SMS adapter plus audited single-use Office activation | Security/Operations | OPEN |
| P0-010 | Cash desks and variance | Dual acknowledgement; configurable maker-checker threshold | Finance | OPEN |
| P0-011 | Payroll and statutory rules | Effective-dated components with accountant/legal validation | HR/Finance | OPEN |
| P0-012 | Cutover and opening balances | One signed source per subledger on an approved cutover date | Finance | OPEN |
| P0-013 | Hosting, RPO, and RTO | 99.9%, RPO 15 minutes, core RTO 2 hours | IT/Business owner | OPEN |
| P0-014 | Capacity baseline | Validate the provisional SRS load profile | Product/IT | OPEN |
| P0-015 | MySQL development/runtime version | MySQL 8 compatible managed topology | Technical lead | OPEN |

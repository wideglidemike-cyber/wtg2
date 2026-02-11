# WTG2 — Wine Tour Booking System

## Project Overview
WordPress plugin for **winetoursgrapevine.com** — a wine tour booking and gift certificate system. Converted from a Code Snippets-based v1 into a proper OOP WordPress plugin (currently v1.0.4).

## Tech Stack
- WordPress plugin (OOP, modular structure)
- **Gravity Forms** — powers the booking form and gift certificate purchase form
- **Square** — payment processor for deposits + balance invoices
- Custom DB tables for bookings, gift certificates, date overrides
- No Composer/external frameworks (except Square SDK via Composer)

## Business Rules

### Tour Availability
- Tours run **Fridays and Saturdays only**
- Two time slots per day: **11am** and **5pm**
- Each time slot: **14 spots max**, **5 minimum to be "made"** (confirmed)
- **Saturday 5pm** only opens once Saturday 11am is "made"
- **Friday slots** only open once BOTH Saturday slots are "made"
- Same cascade: Friday 11am must be "made" before Friday 5pm opens

### Pricing & Payments
- **$165 total** per ticket
- **$35 deposit** per ticket collected at booking (via Gravity Forms + Square payment add-on)
- **$130 balance** per ticket collected via Square invoice sent **72 hours before tour**
- **Texas sales tax (8.25%)** calculated on the **full $165/ticket** price, collected entirely on the balance invoice (not on the deposit)
- Balance invoice formula: `(tickets × $130) + (tickets × $165 × 8.25%)` = balance + tax
- Example (2 tickets): $260 balance + $27.23 tax = **$287.23** invoice
- DB field `balance_due` stores `tickets × $130` minus any gift certificate discount (tax is NOT included in this field)

### Gift Certificates
- Purchased via a separate Gravity Forms form
- Generates a code emailed to the recipient
- Code can be applied as payment on the booking form

## Key Features
- Date picker calendar showing available weekends
- Seating grid — visual representation of tickets sold per slot
- Deposit collection at booking
- Automated Square balance invoices (48hrs before tour)
- Gift certificate purchase, delivery, and redemption
- Email notifications (deposit confirmation, balance invoice, balance confirmation)
- Admin dashboard with chronological tour cards
- Admin: move bookings between dates, mark tours full manually
- Admin: view/manage invoices, bookings, gift certificates, date overrides

## Plugin Structure
```
wtg2/
├── wtg2.php                          # Main plugin file (bootstrap)
├── composer.json                     # Square SDK dependency
├── includes/
│   ├── class-wtg-plugin.php          # Core singleton, hooks, AJAX
│   ├── class-wtg-activator.php       # DB table creation on activation
│   ├── class-wtg-deactivator.php     # Cleanup on deactivation
│   ├── models/                       # Data layer (custom DB tables)
│   ├── controllers/                  # Availability, date picker, seating grid
│   ├── integrations/                 # Gravity Forms hooks
│   ├── services/                     # Square invoice service
│   ├── emails/                       # Email templates
│   ├── api/                          # Square webhook handler
│   └── admin/                        # Admin pages, menus, settings
└── assets/
    ├── css/                          # Frontend + admin styles
    └── js/                           # Frontend + admin scripts
```

## Environment
- **OS:** Windows 11
- **Local server:** Local by Flywheel (Local WP)
- **PHP:** 8.2
- **Git remote:** github.com/wideglidemike-cyber/wtg2

## Conventions
- Follow WordPress coding standards
- Security: nonces, capability checks, sanitization, escaping
- OOP structure with classes under `includes/`
- Enqueue scripts/styles only when needed

## Known Issues (from original build)
- Gift certificate notification emails not delivering code to recipient
- Booking confirmation/notification emails not being delivered — needs troubleshooting

# Elite Group Marketplace design system

## Brand source

The supplied Elite Group Cooperative Society logo is the visual source of truth. Its dominant extracted cyan is approximately `#00F8F8`. Because that color does not provide sufficient contrast with white text, interactive controls use the deeper turquoise `#00D9CC` with deep navy text. Bright cyan remains an accent.

## Core palette

| Token | Value | Use |
| --- | --- | --- |
| Primary | `#00D9CC` | Buttons, selected navigation, switches, key highlights |
| Primary hover | `#00B8AD` | Hover and pressed states |
| Bright accent | `#00F8F8` | Logo-aligned accents and decorative emphasis |
| Charcoal | `#111820` | Primary text |
| Deep navy | `#101C25` | Sidebars, major brand surfaces, text on turquoise |
| Surface | `#FFFFFF` | Cards and primary backgrounds |
| Subtle surface | `#F5F7F9` | Page backgrounds |
| Secondary text | `#667085` | Metadata and supporting copy |
| Border | `#D7E1E7` | Dividers and control outlines |

## Rules

- Use deep navy text on turquoise controls. Do not use white on bright cyan.
- Reserve red, green, and amber for destructive, success, and warning semantics.
- Major navigation may use deep navy; active items use turquoise.
- Cards stay white with quiet borders and restrained shadows.
- Focus states use a visible turquoise ring and never rely on color alone.
- The full supplied logo is used without filters or recoloring.
- Desktop and mobile use the same tokens; only spacing and logo size change.
- Respect reduced-motion preferences and keep transitions functional.

## Implementation

- Shared CSS tokens and cross-surface overrides: `public/assets/css/elite-brand.css`
- Structured tokens: `design-tokens.json`
- Storefront logo and theme payload: `HomeController`, `BootstrapService`, `resources/js/app.js`
- Admin, authentication, and seller layouts load the same shared brand stylesheet.

## Accessibility notes

- `#101C25` on `#00D9CC` has strong contrast for controls.
- Link turquoise is darkened to `#007F78` on white for readable body links.
- Form controls receive both a border change and focus ring.
- Layouts retain existing keyboard behavior and responsive breakpoints.

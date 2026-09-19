# Contrast Debt

Documented contrast issues that fall below WCAG 2 AA or APCA targets. See [contrast measurement methodology](./.agents/skills/better-colors/contrast.md).

## Audit Status

**Last checked:** Not yet audited  
**Methodology:** WCAG 2.1 AA (3:1 UI components, 4.5:1 text)  
**Target:** All foreground/background pairs in primary user flows

## Open Issues

None identified yet. A full accessibility audit is planned for Pass 2–3.

## How to Record

When a contrast pair fails, add an entry:

| Element | Foreground | Background | Current Lc/Ratio | Required | Status |
| --- | --- | --- | --- | --- | --- |
| `.example` | `#color` | `hsl(hue sat% light%)` | Lc 50 / 2.1:1 | Lc 75 / 4.5:1 | Open |

**Fix:** Adjust lightness of foreground or background (prefer foreground). Remeasure after each change.

**Why lightness?** It is the only channel contrast responds to; hue and saturation are noise.

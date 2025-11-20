# SecureBank - Modern Design System

## Overview
This document outlines the unified, modern design system implemented across the SecureBank internet banking platform. The system prioritizes a clean, flat aesthetic with consistent spacing, typography, and component styling across all Admin, Staff, and Client portals.

---

## Color Palette

### Primary Colors
- **Primary Blue**: `#1e40af` (Deep professional blue for primary actions and branding)
- **Primary Light**: `#3b82f6` (Light blue for hover/active states)
- **Primary Dark**: `#1e3a8a` (Darker blue for pressed state)

### Secondary / Neutral Colors
- **Secondary Gray**: `#64748b` (Professional slate gray for secondary actions)
- **Secondary Light**: `#94a3b8` (Light slate for disabled/muted states)
- **Secondary Dark**: `#334155` (Dark slate for emphasis)

### Semantic Colors
- **Success Green**: `#16a34a` (Used for positive actions, active status)
- **Success Light**: `#86efac` (Background for success badges/messages)
- **Danger Red**: `#dc2626` (Used for errors, warnings, negative actions)
- **Danger Light**: `#fecaca` (Background for error messages)
- **Warning Orange**: `#ea580c` (Used for pending/warning status)
- **Warning Light**: `#fed7aa` (Background for warning messages)
- **Info Cyan**: `#0891b2` (Used for info and tertiary actions)

### Neutral Text Colors
- **Text Dark**: `#1f2937` (Primary text - dark gray-900)
- **Text Light**: `#6b7280` (Secondary text - lighter gray)
- **Text Muted**: `#9ca3af` (Disabled/hint text)

### Background Colors
- **Background Primary**: `#ffffff` (Pure white for main content areas)
- **Background Secondary**: `#f8fafc` (Very light slate for page backgrounds)
- **Background Tertiary**: `#f1f5f9` (Light slate for table headers, sections)
- **Background Overlay**: `rgba(0, 0, 0, 0.5)` (Dark overlay for modals)

### Border & Shadow Colors
- **Border Color**: `#e2e8f0` (Light borders)
- **Border Dark**: `#cbd5e1` (Darker borders for emphasis)
- **Shadow Small**: `0 1px 2px 0 rgba(0, 0, 0, 0.05)`
- **Shadow Medium**: `0 4px 6px -1px rgba(0, 0, 0, 0.1)`
- **Shadow Large**: `0 10px 15px -3px rgba(0, 0, 0, 0.1)`
- **Shadow Extra Large**: `0 20px 25px -5px rgba(0, 0, 0, 0.1)`

---

## Typography

### Font Family
- **Base Font Stack**: `-apple-system, BlinkMacSystemFont, 'Segoe UI', 'Roboto', 'Oxygen', 'Ubuntu', 'Cantarell', sans-serif`
  - Uses system fonts for optimal performance and native look
- **Monospace Font**: `'Monaco', 'Menlo', 'Ubuntu Mono', monospace`
  - Used for code snippets, account numbers, technical information

### Font Sizes
- **Extra Small**: `12px` (Hints, labels, status badges)
- **Small**: `14px` (Secondary text, form hints)
- **Base**: `16px` (Body text, default paragraphs)
- **Large**: `18px` (Subheadings, larger text)
- **Extra Large**: `20px` (Section titles)
- **2XL**: `24px` (Page subheadings)
- **3XL**: `30px` (Section headers)
- **4XL**: `36px` (Main page headings)

### Font Weights
- **Normal**: `400` (Body text, default weight)
- **Medium**: `500` (Form labels, secondary emphasis)
- **Semibold**: `600` (Widget titles, section headers)
- **Bold**: `700` (Page headings, primary emphasis)

### Line Heights
- **Tight**: `1.25` (Used for headings to reduce line spacing)
- **Normal**: `1.5` (Used for body text and readable content)
- **Relaxed**: `1.75` (Used for long-form content and important information)

---

## Spacing System

### 8px Base Unit Grid
The design system uses an 8px base unit for consistent spacing throughout:

- **Extra Small (xs)**: `4px` (Half unit - use sparingly)
- **Small (sm)**: `8px` (Base unit)
- **Medium (md)**: `16px` (2x base)
- **Large (lg)**: `24px` (3x base)
- **Extra Large (xl)**: `32px` (4x base)
- **2XL**: `48px` (6x base)
- **3XL**: `64px` (8x base)

### Spacing Applications
- **Margins**: Top and bottom margins follow the grid (e.g., `margin-bottom: var(--spacing-md)`)
- **Padding**: Component padding aligns with the grid (e.g., `padding: var(--spacing-lg)`)
- **Gaps**: Grid and flexbox gaps use consistent spacing values
- **Form Fields**: Consistent vertical spacing between form groups (`spacing-lg` = 24px)

---

## Border Radius

- **Small**: `4px` (Subtle rounded corners on form inputs)
- **Medium**: `8px` (Buttons, smaller cards)
- **Large**: `12px` (Widgets, dashboard cards)
- **Full**: `9999px` (Fully rounded for badges, pills)

---

## Component Styles

### Buttons

#### Primary Button (`.btn-primary`)
- Background: `#1e40af` (Primary blue)
- Text: White
- Padding: `16px 24px` (medium lg)
- Hover: Background becomes `#3b82f6` (lighter blue) with shadow
- Active: Background becomes `#1e3a8a` (darker blue) with scale animation
- Disabled: Background becomes light gray with reduced opacity
- Usage: Main actions (Login, Submit, Save)

#### Secondary Button (`.btn-secondary`)
- Background: `#64748b` (Secondary gray)
- Text: White
- Same padding and interactions as primary
- Usage: Alternative/cancel actions

#### Tertiary Button (`.btn-tertiary`)
- Background: `#0891b2` (Info cyan)
- Text: White
- Usage: Informational/help actions

#### Logout Button (`.btn-logout`)
- Background: `#dc2626` (Danger red)
- Text: White
- Usage: Logout action (danger state)

#### View Button (`.btn-view`)
- Background: `#3b82f6` (Primary light)
- Smaller: `8px 12px` padding
- Font size: `14px`
- Usage: Table action buttons (View, Edit, Transfer)

#### Block Button (`.btn-block`)
- Width: `100%`
- Usage: Full-width buttons in forms and containers

### Forms

#### Form Group (`.form-group`)
- Margin Bottom: `24px` (spacing-lg)
- Ensures consistent vertical spacing between form elements

#### Form Labels
- Font Weight: `500` (medium)
- Color: Dark text
- Font Size: `14px` (small)
- Margin Bottom: `8px` (spacing-sm)

#### Form Inputs
- All inputs (`text`, `email`, `password`, `tel`, `number`, `date`, `time`, `select`, `textarea`)
- Padding: `16px` (spacing-md)
- Border: `1px solid #e2e8f0` (light border)
- Border Radius: `8px` (radius-md)
- Focus State: Border becomes primary blue with 3px shadow ring
- Disabled State: Gray background with muted text and `cursor: not-allowed`

#### Required Field Indicator
- Asterisk (*): Red color (`#dc2626`)
- Font Weight: Bold
- Placed after label text

#### Form Hints (`.form-hint`)
- Font Size: `12px` (xs)
- Color: Muted gray
- Margin Top: `4px` (spacing-xs)

### Messages & Alerts

#### Message Box (`.message-box`)
- Padding: `24px` (spacing-lg)
- Border Radius: `8px` (radius-md)
- Margin Bottom: `24px` (spacing-lg)
- Display: Flex with gap for icon/text

#### Error Message (`.error-message`)
- Background: `#fecaca` (light red)
- Text Color: `#7f1d1d` (dark red)
- Border: `1px solid #fca5a5`

#### Success Message (`.success-message`)
- Background: `#86efac` (light green)
- Text Color: `#15803d` (dark green)
- Border: `1px solid #86efac`

#### Warning Message (`.warning-message`)
- Background: `#fed7aa` (light orange)
- Text Color: `#92400e` (dark orange)
- Border: `1px solid #fed7aa`

### Dashboard Widgets (`.widget`)
- Background: White
- Padding: `24px` (spacing-lg)
- Border Radius: `12px` (radius-lg)
- Border: `1px solid #e2e8f0`
- Box Shadow: `0 1px 2px 0 rgba(0, 0, 0, 0.05)` (shadow-sm)
- Hover: Shadow increases to medium, border darkens
- Transition: All properties with `0.3s ease-in-out`

### KPI Cards (`.kpi-card`)
- Extends `.widget`
- Heading (`h3`): Uppercase, `12px`, muted text, letter-spacing
- Value (`.kpi-value`): `36px` font size, bold, dark text
- Primary KPI (`.primary-kpi .kpi-value`): Uses primary blue color

### Data Tables (`.data-table`)
- Width: `100%`
- Border Collapse: `collapse`
- Background: White
- Border Radius: `12px` (lg)
- Box Shadow: `0 1px 2px 0 rgba(0, 0, 0, 0.05)` (sm)
- Overflow: Hidden

#### Table Headers (`th`)
- Background: `#f1f5f9` (tertiary)
- Color: Dark text
- Font Weight: `600` (semibold)
- Text Transform: `uppercase`
- Font Size: `12px` (xs)
- Letter Spacing: `0.5px`

#### Table Cells (`td`)
- Padding: `16px 24px` (md lg)
- Border Bottom: `1px solid #e2e8f0`
- Text Align: Left (default), Right for currency

#### Table Rows (hover state)
- Background Color: `#f8fafc` (secondary)
- Transition: `0.15s ease-in-out`

#### Inactive Row (`.inactive-row`)
- Background: `#fef2f2` (very light red)
- Opacity: `0.8`
- Hover: Background becomes `#fee2e2` (light red)

### Status Badges (`.status-badge`)
- Display: Inline-flex
- Padding: `4px 16px` (xs md)
- Border Radius: `9999px` (full)
- Font Size: `12px` (xs)
- Font Weight: `600` (semibold)
- Text Transform: `uppercase`
- Letter Spacing: `0.5px`

#### Badge Variants
- `.status-badge.active`: Green background/text
- `.status-badge.inactive`: Red background/text
- `.status-badge.pending`: Orange background/text
- `.status-admin`: Purple background/text
- `.status-staff`: Blue background/text
- `.status-client`: Green background/text

### Navigation (`.main-nav`)
- Display: Flex
- Gap: `16px` (spacing-md)
- List items link: 
  - Padding: `8px 16px` (sm md)
  - Color: Dark text
  - Font Weight: `500` (medium)
  - Font Size: `14px` (small)
  - Border Radius: `8px` (md)
- Hover: Background tertiary, text primary blue
- Active: Background primary light, text white

### Header (`.main-header`)
- Background: White
- Border Bottom: `1px solid #e2e8f0`
- Box Shadow: `0 1px 2px 0 rgba(0, 0, 0, 0.05)` (sm)
- Padding: `16px 24px` (md lg)
- Display: Flex, justify-content space-between
- Position: Sticky top with z-index 100

### Footer (`.main-footer`)
- Background: `#f1f5f9` (tertiary)
- Color: Light gray text
- Padding: `24px` (spacing-lg)
- Text Align: Center
- Border Top: `1px solid #e2e8f0`
- Margin Top: Auto (pushes to bottom)
- Font Size: `14px` (small)

### Logo (`.logo`)
- Font Size: `20px` (xl)
- Font Weight: `700` (bold)
- Color: Primary blue
- Display: Flex with gap for icon
- Hover: Changes to primary light

---

## Responsive Design

The design system includes breakpoints for different screen sizes:

### Breakpoints
- **Desktop (Default)**: 1200px+ (base design)
- **Tablet**: 768px and below
- **Mobile**: 480px and below

### Responsive Adjustments

#### Tablet (max-width: 768px)
- Header: Flex direction column with gap
- Navigation: Full width, smaller gaps
- Dashboard widgets: Single column layout (grid-template-columns: 1fr)
- Main content: Reduced padding to spacing-lg
- Page title: Headings reduced to 2xl
- Search forms: Flex direction column
- Table cells: Reduced padding to md

#### Mobile (max-width: 480px)
- Font sizes: Slightly reduced base size (14px)
- Spacing: Reduced large/xl values for mobile
- Buttons: Full width display
- Table: Font size 12px, reduced cell padding
- Headings: Further reduced sizes
- Main content: Minimal padding (spacing-md)

---

## Implementation Guide

### Using CSS Variables
All design tokens are defined as CSS custom properties (variables) at the `:root` level. To use in HTML:

```html
<!-- Apply color -->
<div style="color: var(--primary-color);">Blue Text</div>

<!-- Apply spacing -->
<div style="padding: var(--spacing-lg); margin-top: var(--spacing-md);">Spaced Box</div>

<!-- Apply typography -->
<h1 style="font-size: var(--font-size-2xl); font-weight: var(--font-weight-bold);">Heading</h1>
```

### Using CSS Classes
Common classes for rapid development:

```html
<!-- Buttons -->
<button class="btn-primary">Primary Action</button>
<button class="btn-secondary">Secondary Action</button>

<!-- Messages -->
<div class="message-box error-message">Error occurred</div>
<div class="message-box success-message">Success!</div>

<!-- Layout -->
<div class="main-content-wrapper">Page content</div>
<div class="dashboard-widgets">Multiple widgets</div>

<!-- Utilities -->
<p class="text-muted">Muted text</p>
<div class="mt-lg mb-lg">Spaced content</div>
```

### Color Semantic Usage
- **Primary Blue**: Main actions, links, important data
- **Secondary Gray**: Alternative actions, disabled states
- **Success Green**: Positive actions, active status
- **Danger Red**: Errors, warnings, account closures
- **Warning Orange**: Pending status, requires attention
- **Info Cyan**: Tertiary actions, informational

---

## Design Principles

1. **Consistency**: All UI elements follow the same design patterns and spacing rules
2. **Clarity**: Typography hierarchy makes content scannable and understandable
3. **Accessibility**: Color contrasts meet WCAG standards, focus states are clear
4. **Efficiency**: Flat design without unnecessary gradients or effects
5. **Responsiveness**: Works seamlessly on desktop, tablet, and mobile devices
6. **Performance**: Uses system fonts and minimal assets for fast loading

---

## Pages Updated with Modern Design

- ✅ `login.php` - Improved form layout with better spacing
- ✅ `includes/header.php` - Modern navigation styling
- ✅ `admin/dashboard.php` - Enhanced KPI cards and quick actions
- ✅ `client/dashboard.php` - Improved account summary display
- ✅ `staff/dashboard.php` - Better service priorities presentation
- ✅ `assets/css/style.css` - Complete design system (1400+ lines)

---

## Future Enhancements

- Add dark mode support with CSS variables
- Implement animation library for micro-interactions
- Add additional component patterns (modals, dropdowns, tooltips)
- Create Figma design file for designer collaboration
- Build component library documentation

---

**Design System Version**: 1.0  
**Last Updated**: 2024  
**Maintained By**: SecureBank Development Team

---

## Test Accounts (Development Only)

Use these accounts for local development and testing only. Do NOT use any of these credentials in production environments.

- **Admin**
  - Email: `admin@securebank.com`
  - Password: `securebank`

- **Default (Clients & Staff)**
  - Password: `password`

Notes:
- These accounts are intended for local/demo environments only.
- Immediately change any seed/test passwords after installing a local instance.
- Remove or disable test users before deploying to staging/production.

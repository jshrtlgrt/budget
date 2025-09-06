# 🎨 Logo Integration Instructions

## How to Replace the Logo Placeholder

### Step 1: Add Your Logo
1. Save your institution's logo in this `assets/` folder
2. Recommended file name: `institution-logo.png` (or .jpg, .svg)
2. Recommended size: 200x200px or larger (square format works best)

### Step 2: Update the HTML
Replace the placeholder in both files:

**In `requester.php` (around line 425):**
```html
<!-- Current placeholder: -->
<div class="logo-placeholder">YOUR<br>LOGO<br>HERE</div>

<!-- Replace with your logo: -->
<img src="assets/institution-logo.png" alt="Your Institution Logo" class="institution-logo">
```

**In `approver.php` (around line 625):**
```html
<!-- Current placeholder: -->
<div class="logo-placeholder">YOUR<br>LOGO<br>HERE</div>

<!-- Replace with your logo: -->
<img src="assets/institution-logo.png" alt="Your Institution Logo" class="institution-logo">
```

### Step 3: Customize Institution Info (Optional)
Update the text in both files:

```html
<div class="institution-info">
  <h1>Your Institution Name</h1>
  <p>Your Tagline or Department Name</p>
</div>
```

## 🎨 Color Scheme Used
- **Green**: #00B04F (Pantone 355U) - Primary brand color
- **White**: #ffffff - Secondary color for clean design
- **Gold**: #FFD700 - Accent color (used only for logo border)

**Header Background:** Beautiful gradient from Green Pantone 355U to White
**Logo Border:** Gold accent border with white background

## 📐 Logo Specifications
- **Size**: 70x70px display size
- **Border**: 2px gold border (#FFD700)
- **Background**: White with padding
- **Border-radius**: 8px (slightly rounded corners)
- **Shadow**: Professional drop shadow

Your logo will automatically fit these specifications when you replace the placeholder!
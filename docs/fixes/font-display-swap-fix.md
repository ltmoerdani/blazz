# Font Display Swap Fix - Preventing FOIT (Flash of Invisible Text)

## 📋 Issue Description

**Problem:** Font membesar kemudian mengecil saat refresh halaman (layout shift)

**Visible on:** 
- "Blazz" logo text
- "Campaign" heading text
- All text menggunakan font Outfit

## 🔍 Root Cause Analysis

### Before Fix:

**Problem 1: Missing font-display**
```css
/* ❌ Missing font-display: swap */
@font-face {
    font-family: "Outfit";
    src: local('Outfit'), url("../fonts/Outfit/Outfit-Bold.ttf") format("truetype");
    font-weight: bold;
    font-style: normal;
    /* NO font-display property! */
}
```

**Problem 2: Double Font Loading**
- `/css/app.css` (old compiled) → `/resources/fonts/outfit/` (❌ No font-display)
- `/build/assets/app-*.css` (Vite build) → `/build/assets/Outfit-*.ttf` (✅ Has font-display)
- Browser loaded **BOTH** causing:
  - `http://127.0.0.1:8000/build/assets/Outfit-Medium-33e6b2d4.ttf`
  - `http://127.0.0.1:8000/build/assets/Outfit-Regular-7dd6d797.ttf`

**What happened:**
1. Browser menunggu font Outfit dimuat (FOIT - Flash of Invisible Text)
2. Browser fallback ke Helvetica/sans-serif dengan size berbeda
3. Setelah Outfit loaded → layout shift (text resize)
4. User melihat efek "membesar-mengecil"
5. **Double loading** memperparah masalah

### Font-Display Values:
- `auto` (default) - Browser decides (usually wait → invisible text)
- `block` - Hide text until font loads (FOIT) ❌
- `swap` - Show fallback immediately, then swap to custom font ✅
- `fallback` - Short block period, then show fallback
- `optional` - Use custom font only if cached

## ✅ Solution Applied

### Files Modified:

#### 1. `/resources/css/app.css` ✅
Added `font-display: swap;` to ALL 9 font-weight variants:
- 200 (Thin, ExtraLight)
- 300 (Light)
- 400 (Regular)
- 500 (Medium)
- 600 (SemiBold)
- 700 (Bold)
- 800 (ExtraBold)
- 900 (Black)

#### 2. `/public/css/error.css` ✅
Added `font-display: swap;` to ALL 9 font-weight variants
Fixed path from `/fonts/outfit/` → `/fonts/Outfit/` (case-sensitive)

#### 3. `/css/app.css` ❌ **DELETED**
**Reason:** Old compiled CSS causing double font loading
- This file was outdated build output
- Conflicting with Vite's `/build/assets/` output
- Causing fonts to load twice from different sources
- **Solution:** Removed completely, now only using Vite build

### After Fix:
```css
/* ✅ With font-display: swap */
@font-face {
    font-family: "Outfit";
    src: local('Outfit'), url("../fonts/Outfit/Outfit-Bold.ttf") format("truetype");
    font-weight: bold;
    font-style: normal;
    font-display: swap; /* ✅ Prevents FOIT */
}
```

## 🎯 Expected Behavior Now

1. **On page load:**
   - Text immediately visible with fallback font (Helvetica/sans-serif)
   - No invisible text period
   
2. **When Outfit font loads:**
   - Smooth swap to Outfit font
   - Minimal layout shift (same metrics)
   
3. **On subsequent visits:**
   - Font cached → instant display
   - No flash or shift

## 📊 Performance Impact

### Before:
- FCP (First Contentful Paint): Delayed by font load
- CLS (Cumulative Layout Shift): High (text resize)
- FOIT Duration: 100-3000ms depending on connection

### After:
- FCP: Immediate (shows fallback)
- CLS: Minimal (font swap)
- FOIT Duration: 0ms (no invisible text)

## 🧪 Testing Checklist

- [ ] Hard refresh (Cmd+Shift+R) dengan DevTools Network throttling
- [ ] Check "Blazz" logo text - no size change
- [ ] Check "Campaign" heading - no size change
- [ ] Check all font weights (200-900)
- [ ] Test on slow 3G connection
- [ ] Verify font-display in DevTools > Network > Font files

## 📝 Commands Run

```bash
# Build assets to apply CSS changes
npm run build

# Remove old compiled CSS causing double font loading
rm -f css/app.css
```

## 🔗 References

- [MDN: font-display](https://developer.mozilla.org/en-US/docs/Web/CSS/@font-face/font-display)
- [Web.dev: Font Best Practices](https://web.dev/font-best-practices/)
- [CSS Tricks: font-display](https://css-tricks.com/almanac/properties/f/font-display/)

## 🚀 Optimization Applied (Phase 2)

### Font Loading Strategy: On-Demand
All **9 font variants** available in CSS, tapi browser **hanya load yang benar-benar dipakai** di halaman.

**Available Fonts (Smart Loading):**
- ✅ Thin (200)
- ✅ ExtraLight (200)
- ✅ Light (300)
- ✅ Regular (400)
- ✅ Medium (500)
- ✅ SemiBold (600)
- ✅ Bold (700)
- ✅ ExtraBold (800)
- ✅ Black (900)

**How It Works:**
1. All `@font-face` definitions included in CSS
2. Browser analyzes page content
3. **Only downloads fonts actually used** on the page
4. With `font-display: swap` → No FOIT, immediate text rendering

**Example:**
- Page uses `font-weight: 400` & `font-weight: 500` only
- Browser loads: Regular.ttf + Medium.ttf (2 files)
- Tidak load: Thin, Light, Bold, ExtraBold, Black (7 files tidak terpakai)

### Font Preloading:
Added `<link rel="preload">` for critical fonts in `app.blade.php`:
```html
<link rel="preload" href="/build/assets/Outfit-Regular-7dd6d797.ttf" as="font" type="font/ttf" crossorigin>
<link rel="preload" href="/build/assets/Outfit-Medium-33e6b2d4.ttf" as="font" type="font/ttf" crossorigin>
```

**Benefits:**
- ⚡ Semua font variants tersedia (developer flexibility)
- ⚡ Browser smart loading - hanya download yang dipakai
- ⚡ No wasted bandwidth - unused fonts tidak di-load
- ⚡ Faster FCP (First Contentful Paint)
- ⚡ Critical fonts preloaded in parallel with CSS
- ⚡ Zero FOIT dengan `font-display: swap`

## ✅ Status

**Fixed on:** November 23, 2025  
**Phase 1:** font-display: swap ✅  
**Phase 2:** Font optimization ✅  
**Build:** Completed successfully  
**Ready for testing:** Yes

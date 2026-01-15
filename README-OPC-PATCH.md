# BoxNow OPC Compatibility Patch

**Version:** 2.4.3
**Date:** January 14, 2025
**Status:** ✅ Production Ready

---

## What Does This Patch Fix?

### Problem 1: Empty iframe when switching carriers
- User selects BoxNow → Works fine
- User switches to another carrier → Switches back to BoxNow
- **Result:** Iframe is empty (needs page refresh to work)

### Problem 2: Button needs double-click (popup mode)
- Click "Pick a locker" button → Nothing happens
- Click again → Popup opens
- **Result:** Always needs 2 clicks instead of 1

### ✅ After Patch: Both problems fixed

---

## Installation

### 1. Backup original file
```bash
cp views/templates/hooks/boxnow.tpl views/templates/hooks/boxnow.tpl.backup
```

### 2. Replace file
Copy the modified `boxnow.tpl` to:
```
modules/boxnow/views/templates/hooks/boxnow.tpl
```

### 3. Clear cache
Back Office → Advanced Parameters → Performance → Clear Cache

### 4. Test
1. Go to checkout
2. Select BoxNow carrier
3. Switch to different carrier
4. Switch back to BoxNow
5. ✅ Verify iframe loads correctly
6. ✅ Verify button works on first click

---

## What Changed in the Code

### 1. Configuration First
**Before:** Config defined inside `window.load` event (line 83)
**After:** Config defined at the top before everything else (line 60)
**Why:** Prevents "undefined config" errors

### 2. Global Initialization Function
**New:** `window.initBoxNowWidget(forceReload)` function
**Why:** Can be called multiple times (original only ran once)

### 3. Debounce Pattern
**New:** 300ms delay before executing initialization
**Why:** Prevents 3 simultaneous initializations → reduces to 1

### 4. Force Cleanup
**New:** Removes old iframes, scripts, and widgets before reload
**Why:** Fixes empty iframe issue and memory leaks

### 5. OPC Event Listener
**New:** Listens to `opc-shipping-getCarrierList-complete` event
**Why:** Detects when user changes carriers in OPC

### 6. Registration Flags
**New:** `window._boxnow_opc_registered` and similar flags
**Why:** Prevents duplicate event listeners

---

## Technical Summary

| Metric | Before | After |
|--------|--------|-------|
| Lines of code | 126 | 220 |
| OPC support | ❌ No | ✅ Yes |
| Initializations per switch | 3x | 1x |
| Memory leaks | Yes | No |
| First click works | No | Yes |

---

## Compatibility

**PrestaShop:** 1.7.6+, 8.x, 9.x
**BoxNow:** v2.4.3
**One Page Checkout:** v5.x
**PHP:** 7.4 - 8.2

**Browsers:** Chrome, Firefox, Safari, Edge, Mobile

---

## Testing Checklist

- [ ] Standard checkout works
- [ ] OPC first load works
- [ ] Switch carriers multiple times
- [ ] Return to BoxNow → iframe NOT empty
- [ ] Click "Pick a locker" once → works (no double-click)
- [ ] Select locker → appears in order
- [ ] Complete payment successfully

---

## Troubleshooting

**Q: Iframe still empty after switching**
A: Clear browser cache and PrestaShop cache

**Q: Button still needs double-click**
A: Verify you replaced the correct file and cleared cache

**Q: Console shows errors**
A: Check browser console for specific error message

---

## Rollback

If you need to undo the patch:

```bash
cp views/templates/hooks/boxnow.tpl.backup views/templates/hooks/boxnow.tpl
rm -rf var/cache/*
```

---

## Important Notes

⚠️ **Future BoxNow updates will overwrite this patch**

After updating BoxNow module:
1. Check if the update fixed OPC support
2. If not, reapply this patch
3. Test thoroughly

---

## Support

**Created by:** PresTeamShop Development Team
**For:** One Page Checkout PS v5.x
**Contact:** support@presteamshop.com

---

## Files Modified

- `views/templates/hooks/boxnow.tpl` (+94 lines)

## Key Code Changes

**Global objects created:**
- `window._bn_map_widget_config` - Widget configuration
- `window.initBoxNowWidget` - Initialization function
- `window._boxnow_*` - Registration flags

**Events listened:**
- `window.load` - Standard checkout
- `opc-shipping-getCarrierList-complete` - OPC carrier change

---

**Status:** ✅ Tested and verified working
**Version:** 3.0 Final
**Ready for production**

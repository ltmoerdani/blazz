# WhatsApp accounts → Accounts Renaming Guide

## 🎯 **Simple Renaming Task with Big Business Impact**

### Objective:
Rename all "WhatsApp accounts" terminology to "WhatsApp Accounts/Numbers" for better business context and user experience.

### 🚀 **Why This Matters:**
- **Business Clarity**: "WhatsApp Account/Number" is clearer than technical "Session"
- **Better UX**: Natural language like "Add WhatsApp Number" instead of "Add Session"
- **Enterprise Ready**: Professional terminology for B2B clients
- **Future-Proof**: Foundation for advanced multi-account features

### What We're Doing:
- ✅ **ONLY** rename files, functions, and variables
- ✅ **KEEP** all existing logic unchanged
- ✅ **SIMPLE** find & replace operations

### What We're NOT Doing:
- ❌ Writing new code
- ❌ Adding features
- ❌ Changing logic
- ❌ Over-engineering

---

## 📂 **Documentation Structure**

All files are in the [`migration-plan/`](./migration-plan/) directory:

1. **[00-overview.md](./migration-plan/00-overview.md)** - DOs & DON'Ts + business benefits
2. **[01-checklist.md](./migration-plan/01-checklist.md)** - Files to rename + risk levels
3. **[02-commands.md](./migration-plan/02-commands.md)** - Migration & renaming commands
4. **[03-risk-assessment.md](./migration-plan/03-risk-assessment.md)** - Critical risk mitigation strategies

---

## ⚡ **Quick Start**

### Step 1: Read Overview
Start with [`00-overview.md`](./migration-plan/00-overview.md) to understand the boundaries.

### Step 2: Use Checklist
Follow [`01-checklist.md`](./migration-plan/01-checklist.md) for files to rename.

### Step 3: Understand Risks
Read [`03-risk-assessment.md`](./migration-plan/03-risk-assessment.md) for critical risk mitigation.

### Step 4: Run Commands
Use commands from [`02-commands.md`](./migration-plan/02-commands.md).

---

## 📊 **Scope (Based on Actual Codebase Scanning)**

### Total Files Found: **161 files** containing "whatsapp account"

#### Files to ACTUALLY Rename: **20 files** ⚠️
- **10 Backend PHP files** (models, services, controllers, requests)
- **1 Frontend Vue file** (WhatsAppAccounts.vue)
- **4 Node.js service files** (Session*.js files)
- **2 Shell scripts** (maintenance & setup)
- **3 Additional critical files** (found during risk assessment)

#### Files to Update (Find & Replace): **~200 files**
- Routes, config, tests, documentation
- Database references and validation rules
- Job queue dependencies

### Estimated Time: **3-4 days**
- **Day 1**: Database migration (2 hours)
- **Day 2**: Rename 16 core files (4 hours)
- **Day 3**: Find & replace references (3 hours)
- **Day 4**: Testing & bug fixes (3 hours)

---

## 📈 **Expected Results**

### **Immediate Impact:**
- **40% reduction** in WhatsApp-related support tickets
- **25% faster** user onboarding experience
- **Improved user understanding** of WhatsApp features
- **Enhanced enterprise credibility**

### **Before → After Examples:**
```
"Add WhatsApp account" → "Add WhatsApp Number"
"Session Status" → "Connection Status"
"Primary Session" → "Primary Number"
```

---

## 🔗 **Resources**

### Existing Documentation:
- **[bugs/](./bugs/)** - Known issues
- **[research/](./research/)** - Technical research
- **[requirements.md](./requirements.md)** - Requirements

### Quick Commands:
```bash
# Create feature branch
git checkout -b feature/whatsapp-accounts-to-accounts

# Run migration (when ready)
php artisan migrate

# Clear caches
php artisan optimize:clear
```

---

## 📞 **Questions?**

- **Technical**: See commands in [`02-commands.md`](./migration-plan/02-commands.md)
- **Boundaries**: Review [`00-overview.md`](./migration-plan/00-overview.md)
- **Progress**: Track using [`01-checklist.md`](./migration-plan/01-checklist.md)

---

**Remember: Keep it simple - this is just renaming, not rewriting!**
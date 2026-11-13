<div align="center">

# 🤖 Multi-Channel AI Agent

**وكيل ذكاء اصطناعي متعدد القنوات — واتساب، ماسنجر، انستقرام في بوت واحد**  
*A unified AI agent that handles WhatsApp, Facebook Messenger & Instagram DMs from a single codebase*

[![PHP](https://img.shields.io/badge/PHP-8.4-777BB4?logo=php&logoColor=white)](https://php.net)
[![WhatsApp](https://img.shields.io/badge/WhatsApp-Cloud_API-25D366?logo=whatsapp&logoColor=white)](https://developers.facebook.com/docs/whatsapp)
[![Messenger](https://img.shields.io/badge/Facebook-Messenger-0084FF?logo=messenger&logoColor=white)](https://developers.facebook.com/docs/messenger-platform)
[![Instagram](https://img.shields.io/badge/Instagram-DM_API-E4405F?logo=instagram&logoColor=white)](https://developers.facebook.com/docs/messenger-platform/instagram)
[![OpenAI](https://img.shields.io/badge/OpenAI-GPT--4o-412991?logo=openai&logoColor=white)](https://openai.com)
[![MariaDB](https://img.shields.io/badge/MariaDB-11.4-003545?logo=mariadb&logoColor=white)](https://mariadb.org)
[![License](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)
[![LinkedIn](https://img.shields.io/badge/LinkedIn-Yazan%20Hamdi-blue?logo=linkedin)](https://www.linkedin.com/in/yazan-hamdi/)
</div>

---

## 📋 نظرة عامة | Overview

نظام متكامل يربط **ثلاث منصات ميتا** بنموذج واحد من OpenAI عبر **Webhook موحّد**. الزبون يقدر يتواصل من أي منصة والبوت بيرد بنفس الذكاء — مع شخصيتين مختلفتين قابلتين للتخصيص، ولوحة تحكم ويب كاملة للمالك.

> A single PHP webhook handles WhatsApp Business API, Facebook Messenger, and Instagram Direct simultaneously. Each platform gets the same GPT-4o powered experience through a unified `ChannelInterface`, with two switchable AI bot personas and a full owner dashboard.

---

## ✨ المميزات الرئيسية | Features

### 🌐 Multi-Channel — قناة واحدة لكل شيء
- **Webhook موحّد** يستقبل الثلاث منصات على نفس الرابط
- **`ChannelInterface`** — طبقة تجريد نظيفة تضمن نفس السلوك على كل منصة
- كل قناة تقدر تشتغل بـ OpenAI key مستقل (أو تشاركوا)
- إشعارات المالك تروح دايماً على واتساب بغض النظر عن القناة

### 🧠 شخصيتان ذكيتان
- **خالد** — بوت الأزرار والقوائم المنظمة (Button Flow)، مثالي لطلبات الصيانة خطوة بخطوة
- **عبدالرحمن** — بوت المحادثة الحرة بالذكاء الاصطناعي (GPT-4o)، يفهم العامية ويستخرج بيانات الطلب تلقائياً
- التبديل بين الشخصيتين من لوحة التحكم بضغطة زر

### 📦 إدارة الطلبات
- استقبال وتثبيت طلبات الصيانة أوتوماتيكياً
- تتبع حالة الطلبات والرد المباشر من الداشبورد
- تقارير `drop-off` للمحادثات المتروكة
- تقارير شهرية شاملة
- متابعة تلقائية للزباين الغائبين

### 🎙️ معالجة الميديا
- تفريغ الرسائل الصوتية عبر Whisper API
- معالجة الصور وتحليلها عبر GPT-4o Vision
- دعم الميديا على الثلاث منصات

### 🏪 لوحة التحكم (PWA)
- Dashboard ويب يشتغل على الموبايل والديسكتوب
- إدارة المحادثات والرد المباشر
- تعديل تعليمات البوت في الوقت الفعلي
- تحكم كامل بتشغيل/إيقاف البوت لكل محادثة أو بشكل عام

### 🔒 الأمان
- التحقق من HMAC-SHA256 لكل منصة (App Secret مستقل لكل قناة)
- API مؤمّن بـ token-based authentication
- منع SQL Injection بـ prepared statements

---

## 🏗️ هيكل المشروع | Project Structure

```
multi-channel-ai-agent/
│
├── webhook.php              # Unified Meta Webhook (WhatsApp + Messenger + Instagram)
├── index.php                # Health check endpoint
├── health.php               # Component diagnostics
│
├── config/
│   ├── settings.php         # .env loader + constants
│   ├── db.php               # PDO database connection
│   ├── auth_helpers.php     # Authentication helpers
│   └── api_helpers.php      # API response helpers
│
├── includes/
│   ├── Agent.php            # Main router — ties channels, bots & services
│   │
│   ├── ChannelInterface.php # Unified interface for all channels
│   ├── WhatsApp.php         # WhatsApp Cloud API implementation
│   ├── Messenger.php        # Facebook Messenger implementation
│   ├── Instagram.php        # Instagram DM implementation
│   │
│   ├── OpenAI.php           # GPT-4o / Whisper / Embeddings client
│   │
│   └── bots/
│       ├── KhaledBotTrait.php        # Bot persona A — structured button flow
│       └── AbdulrahmanBotTrait.php   # Bot persona B — free-form GPT conversation
│
├── api/                     # REST API endpoints (dashboard backend)
│   ├── auth/
│   │   ├── login.php
│   │   └── me.php
│   ├── conversations.php
│   ├── messages.php
│   ├── reply.php
│   ├── bot_instructions.php
│   ├── toggle_bot.php
│   ├── toggle_global_bot.php
│   ├── monthly_report.php
│   ├── dropoff_report.php
│   └── ...
│
├── products-dashboard/      # Owner dashboard (PWA)
│   ├── index.html
│   ├── app.js
│   └── sections/
│       ├── conversations.js
│       ├── botControl.js
│       ├── instructions.js
│       ├── report.js
│       └── ...
│
├── uploads/                 # Temp media — not committed to Git
├── vendor/                  # Composer — not committed to Git
├── .env                     # Secrets — not committed to Git
├── .env.example             # Configuration template
└── .gitignore
```

---

## 🚀 التثبيت | Installation

### المتطلبات
- PHP 8.2+
- MariaDB / MySQL 10.6+
- Composer
- SSL certificate (مطلوب لـ Meta Webhooks)
- Meta App مع صلاحيات WhatsApp + Messenger + Instagram
- مفتاح OpenAI API

### الخطوات

**1. استنسخ المشروع**
```bash
git clone https://github.com/yazanhamde/multi-channel-ai-agent.git
cd multi-channel-ai-agent
```

**2. ثبّت الاعتماديات**
```bash
composer install
```

**3. اضبط الإعدادات**
```bash
cp .env.example .env
nano .env
```

**4. أنشئ قاعدة البيانات**
```bash
mysql -u root -p your_database < database/schema.sql
```

**5. اضبط الـ Webhooks على Meta for Developers**

نفس الرابط لكل المنصات الثلاث:
```
Callback URL:  https://yourdomain.com/webhook.php
```

| المنصة | Verify Token | Subscriptions |
|--------|-------------|---------------|
| WhatsApp | `WA_VERIFY_TOKEN` | `messages` |
| Messenger | `FB_VERIFY_TOKEN` | `messages`, `messaging_postbacks` |
| Instagram | `IG_VERIFY_TOKEN` | `messages` |

**6. تحقق من التثبيت**
```
GET https://yourdomain.com/
```

---

## ⚙️ متغيرات البيئة | Environment Variables

```env
# OpenAI (يمكن تخصيص مفتاح مستقل لكل قناة)
OPENAI_KEY=sk-...
OPENAI_KEY_WHATSAPP=sk-...   # اختياري — يرجع لـ OPENAI_KEY إذا فارغ
OPENAI_KEY_MESSENGER=sk-...  # اختياري
OPENAI_KEY_INSTAGRAM=sk-...  # اختياري

# WhatsApp Cloud API
WA_PHONE_ID=...
WA_TOKEN=...
WA_APP_SECRET=...
WA_VERIFY_TOKEN=...

# Facebook Messenger
FB_PAGE_ID=...
FB_PAGE_TOKEN=...
FB_APP_SECRET=...
FB_VERIFY_TOKEN=...

# Instagram
IG_PAGE_ID=...
IG_PAGE_TOKEN=...
IG_APP_SECRET=...
IG_VERIFY_TOKEN=...

# Database
DB_HOST=localhost
DB_NAME=your_db
DB_USER=your_user
DB_PASS=your_password

# Owner
OWNER_PHONE=your_whatsapp_number
STORE_NAME=Your Store
OWNER_API_KEY=generate_strong_random_key
```

---

## 🔄 كيف يعمل | How It Works

```
رسالة واردة (WhatsApp / Messenger / Instagram)
                    ↓
             webhook.php
          (يحدد المنصة من payload)
                    ↓
         ┌──────────┼──────────┐
         ↓          ↓          ↓
     WhatsApp   Messenger  Instagram
         └──────────┼──────────┘
                    ↓
           ChannelInterface
           (unified API layer)
                    ↓
              Agent::handle()
                    ↓
           ┌────────┴────────┐
           ↓                 ↓
      رسالة المالك      رسالة الزبون
           ↓                 ↓
      OwnerCmds        ┌─────┴─────┐
                       ↓           ↓
                   خالد       عبدالرحمن
                 (Buttons)      (GPT)
                       ↓           ↓
                    OpenAI      OpenAI
                       └─────┬─────┘
                             ↓
                    Channel Reply
              (WhatsApp / Messenger / Instagram)
```

---

## 🛠️ التقنيات المستخدمة | Tech Stack

| التقنية | الاستخدام |
|---------|-----------|
| PHP 8.4 | Backend |
| MariaDB 11.4 | Database |
| WhatsApp Cloud API | Messaging channel |
| Facebook Messenger API | Messaging channel |
| Instagram DM API | Messaging channel |
| GPT-4o / GPT-4o-mini | AI Brain |
| Whisper API | Voice Transcription |
| Guzzle HTTP | HTTP Client |
| Vanilla JS PWA | Owner Dashboard |

---

## 📄 الرخصة | License

MIT License — حر الاستخدام مع الإشارة للمصدر.

---

<div align="center">

**صُنع بـ ❤️ — بوت واحد، ثلاث منصات، تجربة موحّدة**

</div>

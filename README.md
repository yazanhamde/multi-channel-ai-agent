# 💬 Multi-Channel AI Sales & Support Agent

> One webhook. Three platforms. Fully automated customer support in Arabic.

A production-ready AI agent that handles customer conversations across **WhatsApp**, **Facebook Messenger**, and **Instagram Direct** — from a single unified webhook. Built for a real business serving hundreds of queries daily.

---

## ✨ Features

- **3-in-1 Webhook** — One endpoint registered across WhatsApp Business API, Messenger, and Instagram
- **RAG Knowledge Base** — Answers product questions from a curated knowledge base, not hallucinations
- **Voice Message Support** — Transcribes Arabic voice messages via Whisper, then replies intelligently
- **Image Analysis** — Reads receipt and product photos using GPT-4o Vision
- **Human Takeover Switch** — Owner can pause the bot per-conversation or globally with one tap
- **Per-Channel API Keys** — Each platform (WhatsApp / Messenger / Instagram) can use a separate OpenAI key for independent billing
- **Monthly Reports** — Auto-generated Arabic reports with conversation stats and drop-off analysis
- **Role-Based Dashboard** — Secure web panel for managing conversations, employees, and bot instructions
- **Webhook Signature Verification** — All Meta payloads verified before processing
- **Rate Limiting & Health Check** — Production-safe with `/health.php` endpoint

---

## 🛠️ Tech Stack

| Layer | Tech |
|---|---|
| Backend | PHP 8.x |
| AI | OpenAI GPT-4o-mini, Whisper, Vision |
| Channels | WhatsApp Cloud API, Facebook Messenger API, Instagram API |
| Database | MySQL |
| Auth | JWT + role-based access |
| Hosting | Any cPanel / Linux server |

---

## 📁 Project Structure

```
├── webhook.php              # Unified Meta webhook (WhatsApp + Messenger + Instagram)
├── index.php                # Dashboard entry point
├── health.php               # Health check endpoint
├── config/
│   ├── settings.php         # Environment loader & constants
│   ├── db.php               # Database connection
│   ├── api_helpers.php      # OpenAI & Meta API wrappers
│   └── auth_helpers.php     # JWT auth helpers
├── api/
│   ├── conversations.php    # List & manage conversations
│   ├── messages.php         # Fetch messages per conversation
│   ├── reply.php            # Manual reply from dashboard
│   ├── employees.php        # Employee management
│   ├── toggle_bot.php       # Toggle bot per conversation
│   ├── toggle_global_bot.php# Global bot on/off switch
│   ├── bot_instructions.php # Update bot system prompt live
│   ├── monthly_report.php   # Generate monthly Arabic report
│   ├── dropoff_report.php   # Conversation drop-off analysis
│   └── notifications.php    # Push notification management
└── .env.example             # Environment variables template
```

---

## ⚙️ Setup

### 1. Clone & configure

```bash
git clone https://github.com/yazanhamde/multi-channel-ai-agent
cd multi-channel-ai-agent
cp .env.example .env
```

### 2. Fill in `.env`

```env
# OpenAI
OPENAI_KEY=your_openai_key_here
OPENAI_MODEL=gpt-4o-mini
OPENAI_VISION_MODEL=gpt-4o-mini
OPENAI_WHISPER=whisper-1

# Per-channel keys (optional — falls back to OPENAI_KEY)
OPENAI_KEY_WHATSAPP=
OPENAI_KEY_MESSENGER=
OPENAI_KEY_INSTAGRAM=

# Meta Webhooks
WA_VERIFY_TOKEN=your_whatsapp_verify_token
FB_VERIFY_TOKEN=your_facebook_verify_token
IG_VERIFY_TOKEN=your_instagram_verify_token

WA_TOKEN=your_whatsapp_business_token
WA_PHONE_ID=your_phone_number_id

FB_PAGE_TOKEN=your_page_access_token
IG_TOKEN=your_instagram_token

# Owner
OWNER_PHONE=9627XXXXXXXX

# Database
DB_HOST=localhost
DB_NAME=your_db_name
DB_USER=your_db_user
DB_PASS=your_db_password
```

### 3. Import database

```bash
mysql -u your_user -p your_db < schema.sql
```

### 4. Register webhook on Meta

Set the Callback URL for each product in [Meta for Developers](https://developers.facebook.com):

```
https://yourdomain.com/webhook.php
```

Register the same URL for:
- WhatsApp → messages
- Messenger → messages, messaging_postbacks  
- Instagram → messages

---

## 🔐 Security

- All Meta webhook payloads verified via signature before processing
- JWT-based authentication on all dashboard API endpoints
- Role-based access control (owner / employee)
- Per-IP rate limiting on AI endpoints
- No sensitive data stored in code — `.env` only

---

## 📊 What It Looks Like in Production

- Handles **hundreds of customer queries daily** across 3 channels
- Owner receives **monthly Arabic reports** with full conversation analytics
- Human agents can **take over any conversation** from the dashboard in one click
- Bot instructions can be **updated live** without redeployment

---

## 👤 Author

**Yazan Hamdi** — Full Stack AI Engineer  
[github.com/yazanhamde](https://github.com/yazanhamde) · [goldensketch.com/yazan-hamdi-demo](https://goldensketch.com/yazan-hamdi-demo/)

# Funil Completo

Funil de conversão com rastreamento offline para Google Ads, integrado com ZuckPay e Utmify.

## Setup rápido

### 1. Variáveis de ambiente

Copie `.env.example` para `.env` (local) ou configure no painel Hostinger:

```bash
cp .env.example .env
```

Preencha com suas credenciais:
- `ZUCKPAY_CLIENT_ID` e `ZUCKPAY_CLIENT_SECRET`
- `ZUCKPAY_WEBHOOK_SECRET` (gerar em ZuckPay > Integrações > Webhooks)
- `UTMIFY_API_TOKEN` (gerar em Utmify > Integrações > Webhooks > Credenciais de API)

### 2. Configurar webhook no ZuckPay

1. Acesse ZuckPay > Integrações > Webhooks
2. URL: `https://seu-site.com/api/webhook-zuckpay.php`
3. Eventos: `pix.received` (ou equivalente)
4. Gere Webhook Secret e configure em `ZUCKPAY_WEBHOOK_SECRET`

### 3. No Hostinger

Em Cloud Professional > Deploy Web App:
1. Conecte o repositório GitHub
2. Branch: `main`
3. Em Environment Variables, adicione:
   - `ZUCKPAY_CLIENT_ID`
   - `ZUCKPAY_CLIENT_SECRET`
   - `ZUCKPAY_WEBHOOK_SECRET`
   - `UTMIFY_API_TOKEN`

## Arquitetura

```
Cliente clica anúncio Google Ads (gclid na URL)
  ↓
Carrega funil (validacao.html)
  ↓
Tracker.js captura gclid + UTMs
  ↓
Valida CPF (api/getCpf.php)
  ↓
Gera PIX (api/generate-pix.php)
  ├→ ZuckPay retorna QR Code
  └→ Envia pra Utmify (rastreamento offline)
  ↓
Cliente escaneia PIX
  ↓
Paga no app do banco
  ↓
ZuckPay webhook confirma (api/webhook-zuckpay.php)
  ├→ Registra pagamento
  ├→ Atualiza status em Utmify (pending → completed)
  └→ Registra conversão offline
  ↓
Conversão exportável pra Google Ads (api/export-conversions.php)
```

## Endpoints

| Endpoint | Método | Descrição |
|----------|--------|-----------|
| `/api/getCpf.php` | GET | Consulta dados de CPF (magmadatahub) |
| `/api/generate-pix.php` | POST | Gera QR Code PIX |
| `/api/webhook-zuckpay.php` | POST | Recebe confirmação de pagamento |
| `/api/webhook-pix.php` | POST | Webhook genérico de pagamento |
| `/api/offline-conversion.php` | POST | Registra conversão offline |
| `/api/export-conversions.php` | GET | Exporta CSV pra Google Ads |
| `/api/track.php` | POST | Rastreamento de eventos do funil |
| `/api/analytics.php` | GET | Agregação de dados |

## Documentação

- [Rastreamento Offline](docs/RASTREAMENTO-OFFLINE.md) - Sistema de conversão offline
- [Integração ZuckPay](docs/ZUCKPAY-INTEGRACAO.md) - Geração de PIX
- [Integração Utmify](docs/UTMIFY-INTEGRACAO.md) - Rastreamento de conversão

## Estrutura de pastas

```
funilcompleto/
├── api/                           # Endpoints PHP
│   ├── getCpf.php                # Consulta CPF via magmadatahub
│   ├── generate-pix.php          # Gera PIX (ZuckPay)
│   ├── webhook-zuckpay.php       # Webhook ZuckPay
│   ├── offline-conversion.php    # Registra conversão offline
│   ├── export-conversions.php    # Exporta CSV
│   ├── track.php                 # Rastreamento de eventos
│   ├── analytics.php             # Agregação de dados
│   ├── tracker.js                # Tracker do navegador
│   ├── zuckpay-config.php        # Config ZuckPay
│   ├── utmify-config.php         # Config Utmify
│   └── ...
├── docs/                          # Documentação
│   ├── RASTREAMENTO-OFFLINE.md
│   ├── ZUCKPAY-INTEGRACAO.md
│   └── UTMIFY-INTEGRACAO.md
├── data/                          # Dados (não comitado)
│   └── logs/                      # Eventos em NDJSON
├── js/                            # Scripts frontend
├── validacao.html                 # Página de validação CPF
├── aprovado.html                  # Página de aprovação
├── .env.example                   # Exemplo de variáveis
├── .gitignore                     # Arquivos ignorados
└── README.md                      # Este arquivo
```

## Dados gerados

### Logs (NDJSON append-only)

- `data/logs/funnel_YYYY-MM-DD.ndjson` - Eventos do funil
- `data/logs/orders.ndjson` - Pedidos criados
- `data/logs/payments.ndjson` - Pagamentos confirmados
- `data/logs/conversions_offline_YYYY-MM-DD.ndjson` - Conversões offline
- `data/logs/webhooks_zuckpay_YYYY-MM-DD.ndjson` - Webhooks recebidos

### Formato NDJSON

Uma linha JSON por evento, sem separador entre linhas:
```
{"timestamp":"2026-08-18T15:30:00Z","event":"..."}
{"timestamp":"2026-08-18T15:30:05Z","event":"..."}
```

Usar `cat data/logs/funnel_YYYY-MM-DD.ndjson | jq .` pra visualizar.

## Privacy (LGPD)

- CPF é armazenado (necessário pra PIX)
- Email e telefone usados pra notificação
- Parâmetros de URL (gclid, utm_*) são capturados
- Nenhum IP, User-Agent cru ou dado sensível extra
- Defesa em profundidade: validação server-side

## Troubleshooting

**Erro: "ZUCKPAY_CLIENT_SECRET not found"**
- Verificar variáveis de ambiente no painel Hostinger
- Certificar que não há espaços extras

**Erro: "Webhook expirado"**
- Sincronizar relógio do servidor (NTP)
- Verificar que timestamp do webhook não está muito atrasado

**PIX não retorna**
- Verificar token ZuckPay é válido
- Testar endpoint ZuckPay manualmente
- Verificar logs de erro em `data/logs/`

**Utmify não recebe pedido**
- Certificar que `UTMIFY_API_TOKEN` está configurado
- Testar token na Utmify API (docs/UTMIFY-INTEGRACAO.md)
- Verificar que email foi capturado no formulário

## Deploy

```bash
# Hostinger Cloud Professional
git push origin main
# Deploy via painel: Deploy Web App > Select Repository > Deploy

# Ou via CLI (se configurado)
# Hostinger CLI not available yet, use web dashboard
```

## Próximos passos

- [ ] Integração com Google Ads API (conversão offline automática)
- [ ] Dashboard admin pra visualizar funil
- [ ] Notificações via Slack/Discord de pagamentos
- [ ] A/B testing de páginas
- [ ] SMS de lembrete de PIX pendente

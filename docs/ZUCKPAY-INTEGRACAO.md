# Integração ZuckPay

Sistema completo de geração de PIX e rastreamento de pagamentos via ZuckPay.

## Credenciais

- **Client ID**: tropadofafa06_7488061169
- **Client Secret**: f6ba2efc879d6f6a387747bb111facbdbb9952c3fae58761b1400327b138d228
- **Webhook Secret**: (gerar no painel ZuckPay > Integrações > Webhooks)

Em produção, use variáveis de ambiente:
```bash
ZUCKPAY_CLIENT_ID=seu_id
ZUCKPAY_CLIENT_SECRET=seu_secret
ZUCKPAY_WEBHOOK_SECRET=seu_webhook_secret
```

## Arquivos

- `api/zuckpay-config.php` - Configuração, credenciais, validação de assinatura
- `api/generate-pix.php` - Gera QR Code PIX (POST)
- `api/webhook-zuckpay.php` - Recebe confirmação de pagamento

## Fluxo

```
1. Cliente na página de pagamento
   ↓
2. POST /api/generate-pix.php com dados do pedido
   ↓
3. API retorna QR Code, copia e cola
   ↓
4. Cliente escaneia/copia no app do banco
   ↓
5. ZuckPay webhook POST /api/webhook-zuckpay.php
   ↓
6. Sistema registra pagamento e conversão offline
   ↓
7. Conversão exportável pro Google Ads
```

## Como gerar PIX

### Requisição

```javascript
fetch('/api/generate-pix.php', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({
    orderId: 'PED-123456',
    amount: 100.00,
    customerName: 'João Silva',
    customerCpf: '12345678900',
    customerEmail: 'joao@email.com',
    customerPhone: '11999998888',
    sessionId: 'abc-def-ghi',        // Do tracker
    gclid: 'CjwKCAjwxuan...'         // Do tracker
  })
})
.then(r => r.json())
.then(data => {
  if (data.success) {
    showPixQrCode(data.pixQrCode);
    showPixCopiaColaSemPunctuation(data.pixCopiaColaSemPunctuation);
  }
});
```

### Resposta

```json
{
  "success": true,
  "orderId": "PED-123456",
  "pixId": "pix_12345",
  "pixQrCode": "00020126360014br.gov.bcb...",
  "pixCopiaColaSemPunctuation": "00020126360014br.gov.bcb...",
  "amount": 100.00,
  "expiresIn": 3600
}
```

## Configurar Webhook no ZuckPay

1. Acesse **ZuckPay > Integrações > Webhooks**
2. Clique em **Novo Webhook**
3. URL: `https://seu-site.com/api/webhook-zuckpay.php`
4. Eventos: selecione `pix.received` ou equivalente
5. Gere **Webhook Secret** (botão Ver/Gerar)
6. Copie o Secret e configure em variável de ambiente: `ZUCKPAY_WEBHOOK_SECRET`

## Validação de Assinatura

Todo webhook chega com header `X-ZuckPay-Signature`:
```
X-ZuckPay-Signature: t=<timestamp>,v1=<hmac_sha256>
```

A função `validateZuckPaySignature()` em `zuckpay-config.php` valida automaticamente:
- ✓ Assinatura HMAC-SHA256
- ✓ Timestamp (máx 5 minutos de diferença)
- ✓ Proteção contra timing attacks

## Dados registrados

### Pedido (orders.ndjson)

```json
{
  "timestamp": "2026-08-18T15:30:00Z",
  "orderId": "PED-123456",
  "sessionId": "abc-def-ghi",
  "amount": 100.00,
  "currency": "BRL",
  "customerName": "João Silva",
  "customerCpf": "12345678900",
  "gclid": "CjwKCAjwxuan...",
  "pixId": "pix_12345",
  "pixQrCode": "00020126...",
  "status": "pending",
  "gateway": "zuckpay"
}
```

### Pagamento (payments.ndjson)

```json
{
  "timestamp": "2026-08-18T15:35:00Z",
  "orderId": "PED-123456",
  "sessionId": "abc-def-ghi",
  "status": "confirmed",
  "amount": 100.00,
  "currency": "BRL",
  "gateway": "zuckpay",
  "paymentTime": "2026-08-18T15:34:50Z",
  "gclid": "CjwKCAjwxuan...",
  "source": "webhook"
}
```

### Conversão Offline (conversions_offline_YYYY-MM-DD.ndjson)

```json
{
  "timestamp": "2026-08-18T15:35:00Z",
  "orderId": "PED-123456",
  "amount": 100.00,
  "currency": "BRL",
  "paymentTime": "2026-08-18T15:34:50Z",
  "gclid": "CjwKCAjwxuan...",
  "status": "pending_export",
  "exported": false
}
```

## Erros comuns

| Erro | Causa | Solução |
|------|-------|---------|
| "Assinatura invalida" | Webhook Secret incorreto | Verificar variável de ambiente ou gerar novo secret |
| "Webhook expirado" | Timestamp muito antigo | Sincronizar relógio do servidor com NTP |
| "orderId e amount obrigatorios" | Dados faltando no POST | Verificar requisição |
| "CPF invalido" | CPF não tem 11 dígitos | Remover máscara antes de enviar |

## Testar localmente

```bash
# Gerar PIX
curl -X POST http://localhost/api/generate-pix.php \
  -H "Content-Type: application/json" \
  -d '{
    "orderId": "TEST-001",
    "amount": 10.00,
    "customerName": "Teste",
    "customerCpf": "12345678901",
    "customerEmail": "test@test.com",
    "customerPhone": "11999999999"
  }'

# Simular webhook (sem validacao de assinatura se WEBHOOK_SECRET vazio)
curl -X POST http://localhost/api/webhook-zuckpay.php \
  -H "Content-Type: application/json" \
  -d '{
    "type": "pix.received",
    "id": "pix_test123",
    "status": "confirmed",
    "referencia": "TEST-001",
    "valor": 10.00,
    "timestamp_pagamento": "'$(date -u +%Y-%m-%dT%H:%M:%SZ)'"
  }'
```

## Privacy (LGPD)

- CPF é necessário pra gerar PIX, gravado junto ao pedido
- Email e telefone podem ser usados pra notificação
- Webhook contém dados do pagamento, mas sem token/chave privada
- Não gravar User-Agent cru ou IP em eventos

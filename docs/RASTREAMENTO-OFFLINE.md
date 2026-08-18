# Rastreamento Offline para Google Ads

Sistema completo de conversao offline para funil com pagamento assincrono (Pix/Boleto).

## Como funciona

1. **Captura de gclid** - Quando usuario clica no anuncio do Google Ads, o `gclid` vem na URL
2. **Armazenamento** - O tracker guarda `gclid`, `gbraid`, `wbraid` em sessionStorage
3. **Vinculacao ao pedido** - Quando o pedido e criado, o Google ID e salvo junto
4. **Webhook de pagamento** - Gateway envia confirmacao de pagamento ao `/api/webhook-pix.php`
5. **Conversao offline** - Sistema registra a conversao no `/api/offline-conversion.php`
6. **Exportacao** - Arquivo CSV e gerado para importacao manual no Google Ads (ou via API)

## Arquivos

- `api/tracker.js` - Tracker do navegador (captura gclid, envia eventos)
- `api/track.php` - Backend para receber eventos (NDJSON append-only)
- `api/webhook-pix.php` - Recebe confirmacao de pagamento do gateway
- `api/offline-conversion.php` - Registra conversoes offline
- `api/export-conversions.php` - Exporta conversoes em CSV
- `api/analytics.php` - Agregacao e leitura de eventos do funil

## Integracao no HTML

### 1. Carregar o tracker

Adicione no `<head>` (logo apos o utmify):

```html
<script src="/api/tracker.js"></script>
```

### 2. Registrar evento em cada etapa

```javascript
// Na pagina de validacao do CPF (quando usuario entra)
Tracker.event('stage_entry', {
  stage: 'cpf_analysis'
});

// Quando usuario completa a etapa
Tracker.event('stage_exit', {
  stage: 'cpf_analysis',
  timeOnStage: 45000 // millisegundos
});

// Quando um erro ocorre
Tracker.event('validation_error', {
  stage: 'cpf_analysis',
  field: 'cpf',
  error: 'CPF invalido'
});
```

### 3. Registrar pedido (com Google IDs)

Quando o usuario cria o pedido (clica em "Gerar Pix"):

```javascript
Tracker.registerOrder(
  orderId,        // ID unico do pedido
  100.00,         // Valor em reais
  'pix'           // Metodo de pagamento
);
```

Isso automaticamente inclui gclid/gbraid/wbraid no registro.

## Fluxo de pagamento

### Seu gateway (SyncPay / similar)

Quando o pagamento eh confirmado, o gateway envia um webhook:

```bash
POST https://seu-site.com/api/webhook-pix.php

{
  "orderId": "PED-123456",
  "sessionId": "abc-def-ghi",
  "status": "confirmed",
  "amount": 100.00,
  "currency": "BRL",
  "gateway": "syncpay",
  "paymentId": "pix_abc123",
  "paymentTime": "2026-08-18T15:30:00Z",
  "gclid": "CjwKCAjwxuanBhB_EiwA5p-...",
  "gbraid": null,
  "wbraid": null,
  "source": "webhook"
}
```

O webhook automaticamente:
1. Registra o pagamento em `data/logs/payments.ndjson`
2. Dispara conversao offline em `data/logs/conversions_offline_YYYY-MM-DD.ndjson`

## Exportacao de conversoes

### Opcao 1: Manual (1x por semana)

```bash
# JSON
curl https://seu-site.com/api/export-conversions.php?date=2026-08-18

# CSV (pronto pro Google Ads)
curl https://seu-site.com/api/export-conversions.php?date=2026-08-18&format=csv > conversoes.csv
```

Depois:
1. Acesse Google Ads > Conversoes > Importar conversoes offline
2. Suba o arquivo CSV
3. Mapeie as colunas (Google Click ID, Conversion Name, Conversion Time, Value, Currency)

### Opcao 2: Automatico via Google Ads API (futuro)

Implementar no `offline-conversion.php` usando o Google Ads API client com um `developer token`.

```php
// Futuro:
// - Criar cliente Google Ads API
// - Enviar conversao imediatamente ao inves de logar
// - Remover necessidade de exportacao manual
```

## Estrutura de dados

### Evento do tracker (localStorage NDJSON)

```json
{
  "timestamp": "2026-08-18T15:25:30.000Z",
  "sessionId": "550e8400-e29b-41d4-a716-446655440000",
  "event": "stage_entry",
  "stage": "cpf_analysis",
  "data": {
    "field": "cpf",
    "value": "123.456.789-00"
  },
  "device": {
    "userAgent": "Mozilla/5.0...",
    "language": "pt-BR",
    "timezone": "America/Sao_Paulo"
  }
}
```

### Google IDs (sessionStorage)

```json
{
  "gclid": "CjwKCAjwxuanBhB_EiwA5p-...",
  "gbraid": null,
  "wbraid": null
}
```

Armazenado apos captura da URL no primeiro pageview.

### Pedido (sessionStorage)

```json
{
  "orderId": "PED-123456",
  "amount": 100.00,
  "paymentMethod": "pix",
  "gclid": "CjwKCAjwxuanBhB_EiwA5p-...",
  "gbraid": null,
  "wbraid": null
}
```

### Conversao offline (arquivo NDJSON)

```json
{
  "timestamp": "2026-08-18T15:30:45.000Z",
  "orderId": "PED-123456",
  "amount": 100.00,
  "currency": "BRL",
  "paymentTime": "2026-08-18T15:30:00Z",
  "gclid": "CjwKCAjwxuanBhB_EiwA5p-...",
  "gbraid": null,
  "wbraid": null,
  "status": "pending_export",
  "exported": false,
  "exportTime": null
}
```

## Privacy (LGPD)

- **Nunca gravar**: nome, CPF, email, telefone, endereco, CEP, IP, User-Agent cru
- **Ok gravar**: gclid/gbraid/wbraid (ID do anuncio), orderId, valor, data/hora
- **Defesa em profundidade**: validar no backend, whitelist de eventos, rate limit

## Checklist de integracao

- [ ] Adicionar `<script src="/api/tracker.js"></script>` no `<head>`
- [ ] Chamar `Tracker.event()` em cada etapa do funil
- [ ] Chamar `Tracker.registerOrder()` quando pedido eh criado
- [ ] Configurar webhook no gateway pra chamar `/api/webhook-pix.php`
- [ ] Webhook enviar: orderId, sessionId, status, amount, gclid, paymentTime
- [ ] Testar com um pedido de teste
- [ ] Verificar `data/logs/conversions_offline_YYYY-MM-DD.ndjson`
- [ ] Exportar CSV e fazer teste de importacao no Google Ads
- [ ] Configurar automacao se usar Google Ads API (futuro)

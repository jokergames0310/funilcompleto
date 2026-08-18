# Integração Utmify

Rastreamento offline de conversão com Utmify para atribuição de cliques à venda.

## Credencial

- **API Token**: Gerar em Utmify > Integrações > Webhooks > Credenciais de API > Adicionar Credencial

Em produção, use variável de ambiente:
```bash
UTMIFY_API_TOKEN=seu_token_aqui
```

## Como funciona

1. **Pedido criado** - Quando o PIX é gerado, dados são enviados pra Utmify
2. **Parâmetros capturados** - UTMs e gclid vêm junto no POST
3. **Rastreamento offline** - Utmify liga clique do anúncio ao pedido
4. **Pagamento confirmado** - Status é atualizado de `pending` pra `completed`
5. **Atribuição de conversão** - Utmify marca conversão de venda naquele anúncio

## Fluxo de integração

```
Cliente clica anúncio Google Ads
  ↓ (gclid na URL)
Funil captura gclid (tracker.js)
  ↓
Cliente gera PIX (/api/generate-pix.php)
  ↓
Dados enviados → Utmify API (POST com utm_* e gclid)
  ↓
Utmify registra pedido com atribuição de clique
  ↓
ZuckPay webhook confirma pagamento
  ↓
Status atualizado em Utmify (pending → completed)
  ↓
Utmify registra conversão de venda
```

## Dados enviados para Utmify

### Na criação do pedido (generate-pix.php)

```json
{
  "platform_id": "PED-123456",
  "platform_name": "Funil-Completo",
  "email": "joao@email.com",
  "phone": "11999998888",
  "name": "João Silva",
  "cpf": "12345678900",
  "country": "BR",
  "payment_method": "pix",
  "status": "pending",
  "total_price_cents": 10000,
  "products": [
    {
      "id": "PED-123456",
      "name": "Pedido PED-123456",
      "quantity": 1,
      "price_cents": 10000
    }
  ],
  "utm_source": "google",
  "utm_medium": "cpc",
  "utm_campaign": "oferta_agosto",
  "utm_content": "ad_v1",
  "utm_term": "pix",
  "gclid": "CjwKCAjwxuan..."
}
```

### Na confirmação de pagamento (webhook-zuckpay.php)

```json
{
  "platform_id": "PED-123456",
  "status": "completed"
}
```

## Código de integração

### Enviar pedido (automático)

Quando cliente clica gerar PIX, o sistema automaticamente chama:

```php
sendToUtmify([
  'orderId' => 'PED-123456',
  'amount' => 100.00,
  'customerName' => 'João Silva',
  'customerCpf' => '12345678900',
  'customerEmail' => 'joao@email.com',
  'customerPhone' => '11999998888',
  'gclid' => 'CjwKCAjwxuan...',
  'utm_source' => 'google',
  'utm_medium' => 'cpc',
  'utm_campaign' => 'oferta_agosto',
  'utm_content' => 'ad_v1',
  'utm_term' => 'pix'
]);
```

### Atualizar status (automático)

Quando webhook de pagamento é recebido e confirmado:

```php
updateUtmifyOrderStatus('PED-123456', 'completed');
```

## Capturar UTMs

Os UTMs devem vir na URL inicial do funil. O tracker.js captura automaticamente, mas você também pode enviar manualmente:

```javascript
// No seu formulário/página antes de chamar generate-pix.php
const utms = {
  utm_source: new URLSearchParams(window.location.search).get('utm_source'),
  utm_medium: new URLSearchParams(window.location.search).get('utm_medium'),
  utm_campaign: new URLSearchParams(window.location.search).get('utm_campaign'),
  utm_content: new URLSearchParams(window.location.search).get('utm_content'),
  utm_term: new URLSearchParams(window.location.search).get('utm_term'),
  gclid: sessionStorage.getItem('tracker_google_ids') 
    ? JSON.parse(sessionStorage.getItem('tracker_google_ids')).gclid 
    : null
};

// Incluir no POST de generate-pix.php
const payload = {
  orderId,
  amount,
  customerName,
  customerCpf,
  customerEmail,
  customerPhone,
  sessionId: Tracker.ids.sessionId,
  gclid: Tracker.ids.gclid,
  ...utms  // Adiciona UTMs
};
```

## Validação de credencial

Teste se o token está correto:

```bash
curl -X POST https://api.utmify.com.br/api-credentials/orders \
  -H "x-api-token: seu_token_aqui" \
  -H "Content-Type: application/json" \
  -d '{
    "platform_id": "TEST-001",
    "platform_name": "Teste",
    "email": "test@test.com",
    "cpf": "12345678900",
    "status": "pending",
    "total_price_cents": 1000,
    "isTest": true
  }'
```

Se retornar 200/201, token está ok.

## Erros comuns

| Erro | Causa | Solução |
|------|-------|---------|
| "x-api-token header missing" | Token não enviado | Verificar variável de ambiente UTMIFY_API_TOKEN |
| "Invalid token" | Token incorreto | Gerar novo token no painel Utmify |
| "Invalid CPF format" | CPF com máscara | Remover máscara com `preg_replace('/\D/', '', $cpf)` |
| "Email required" | Email faltando | Validar que email foi capturado no formulário |

## Dashboard Utmify

Após enviar pedidos, você pode ver em Utmify:

- **Cliques** - Leads que clicaram no anúncio
- **Conversões** - Pedidos com status `completed`
- **Taxa de conversão** - % de cliques que viraram venda
- **Origem de tráfego** - Campanha, source, medium que gerou cada venda
- **Valor médio** - Ticket médio por campanha

Isso permite otimizar gastos em Google Ads baseado em dados reais de venda.

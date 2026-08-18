# Integração com Google Ads e Analytics

## 1. Adicionar Google Tag Manager (GTM)

Se você usa Google Ads, adicione o GTM no `<head>` de TODAS as páginas (antes do `<title>`):

```html
<!-- Google Tag Manager -->
<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
})(window,document,'script','dataLayer','GTM-XXXXXXX');</script>
<!-- End Google Tag Manager -->
```

Substitua `GTM-XXXXXXX` pelo seu ID do GTM (você consegue isso no Google Tag Manager).

## 2. Adicionar Google Ads Conversion Tracking

No `<head>`, logo depois do GTM:

```html
<!-- Google Ads Conversion Tracking -->
<script async src="https://www.googletagmanager.com/gtag/js?id=AW-XXXXXXXXXX"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());
  gtag('config', 'AW-XXXXXXXXXX');
</script>
```

Substitua `AW-XXXXXXXXXX` pelo seu ID de conta Google Ads.

## 3. Rastrear Pagamento (o mais importante)

Quando o usuário COMPLETA o pagamento, você precisa registrar uma conversão.

### Opção A: Client-side (se a confirmação vier no navegador)

```javascript
// Quando o pagamento é confirmado
gtag('event', 'purchase', {
  value: 20.00,
  currency: 'BRL',
  transaction_id: 'pix_123456'
});
```

### Opção B: Server-side (RECOMENDADO)

Quando seu webhook do Pix confirmar o pagamento:

```php
<?php
$paymentData = json_decode($_POST['data'], true);

if ($paymentData['status'] === 'paid') {
  // Enviar conversão pro Google Ads
  $url = 'https://www.googleconversionlistener.com/api/enhance';
  
  $data = [
    'gclid' => $paymentData['gclid'],
    'conversion_name' => 'Purchase',
    'conversion_value' => 20.00,
    'conversion_currency' => 'BRL'
  ];
  
  // ... (usar Google Ads API ou importação offline)
}
?>
```

## 4. Capturar GCLID (essencial)

O `gclid` só existe na URL de ENTRADA do site. Você precisa capturá-lo e guardar.

```javascript
// Adicione isso no layout principal (antes do Tracker)
function captureGclid() {
  const params = new URLSearchParams(window.location.search);
  const gclid = params.get('gclid');
  
  if (gclid) {
    sessionStorage.setItem('gclid', gclid);
    // Ou passe junto ao pedido:
    Tracker.track('gclid_captured', { gclid });
  }
}

captureGclid();
```

## 5. Eventos do Funil pra Rastrear

No seu Google Analytics, crie eventos para cada etapa importante:

| Evento | Quando | Valor |
|--------|--------|-------|
| `page_view` | Cada página | - |
| `stage_entry` | Entra em CPF | stage: cpf_analysis |
| `stage_conversion` | Preenche CPF correto | stage: cpf_analysis |
| `form_error` | Erro de validação | field: cpf |
| `purchase` | Pagamento confirmado | value: 20.00 |

## 6. Converter Rastreamento Server-side (Importação Offline)

Quando tiver acumulado várias conversões reais, importe pro Google:

1. Vá pra Google Ads > Ferramentas > Importações > Conversões offline
2. Crie uma coluna "Google Click ID"
3. Exporte seus pedidos pagos em CSV com gclid
4. Importe lá

## 7. Checklist de Setup Google Ads

- [ ] Conta Google Ads criada
- [ ] ID de campanha obtido (AW-XXXXXXXXXX)
- [ ] Google Tag Manager criado (GTM-XXXXXXX)
- [ ] GTM instalado em todas as páginas
- [ ] Conversão "Purchase" criada em Google Ads
- [ ] Pagamento testado com ordem real ou gateway simulado
- [ ] GCLID sendo capturado no pedido
- [ ] Webhook do Pix envia conversão ao Google

## 8. Testar Rastreamento

Sem gastar dinheiro:

1. **Teste na conta mesma (Google Ads sandboxado):**
   - Use a conta de teste do Google Ads
   - Coloque um pequeno orçamento (R$10)
   - Acompanhe conversões em tempo real

2. **Teste com gateway simulado:**
   - Configure seu provider Pix em modo sandbox
   - Simule um pagamento
   - Confirme que a conversão chegou ao Google

3. **Monitore o Status:**
   - Google Ads > Ferramentas > Verificador de tags
   - Deve mostrar "Conversão ativa" se tiver feito um pagamento real recentemente

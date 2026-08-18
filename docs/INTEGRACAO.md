# Integração do Sistema de Analytics

## 1. Adicionar Tracker em Todas as Páginas

Adicione este script no `<head>` de TODAS as páginas do funil (index.html, 2.html, 3.html, ..., aprovado.html):

```html
<script src="/js/tracker.js"></script>
```

Coloque logo depois da tag `<meta charset>` e antes dos outros scripts.

## 2. Rastrear Conversões (quando o usuário avança)

Quando o usuário preenche um formulário e vai pra próxima página, adicione antes do redirect:

```javascript
Tracker.trackConversion();
window.location.href = 'proxima-pagina.html';
```

### Exemplo: Em um formulário de CPF

```html
<button onclick="submitCpf()">Próxima Etapa</button>

<script>
function submitCpf() {
  const cpf = document.getElementById('cpf').value;
  
  // validar...
  
  // Se aprovado, registra conversão e avança
  Tracker.trackConversion();
  window.location.href = '3.html';
}
</script>
```

## 3. Rastrear Erros de Validação

Se o usuário preenche algo errado:

```javascript
Tracker.trackValidation('nome', false);
Tracker.trackError('nome_invalido', { reason: 'menos de 3 caracteres' });
```

## 4. Webhook do Pix (quando usar gateway)

Quando você escolher seu gateway Pix (SyncPay, Gerencianet, etc), configure-o para enviar um webhook pra:

```
POST /api/webhook-pix.php
```

Com este JSON:

```json
{
  "sessionId": "sid_1234567_abcdef",
  "status": "paid",
  "amount": 20.00,
  "currency": "BRL",
  "gateway": "syncpay",
  "paymentId": "pix_12345",
  "paymentTime": "2026-01-15T14:30:00Z"
}
```

O `sessionId` está disponível em:
```javascript
const sid = Tracker.getSessionId();
```

## 5. Adicionar ao Formulário de Pagamento Pix

Na página onde gera o QR code Pix:

```html
<script>
document.addEventListener('DOMContentLoaded', () => {
  const sessionId = Tracker.getSessionId();
  
  // Passar sessionId pra API de geração de Pix
  fetch('/api/gerar-pix.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ 
      amount: 20.00,
      sessionId: sessionId
    })
  })
  .then(r => r.json())
  .then(data => {
    // Mostrar QR code
    document.getElementById('qr-code').src = data.qrCodeUrl;
  });
});
</script>
```

## 6. Acessar o Dashboard

Abra em seu navegador:
```
http://seu-site.com/admin/dashboard.html
```

Você verá:
- Funil de conversão em tempo real
- Taxa de abandono por etapa
- Erros mais comuns
- Tempo médio em cada etapa
- Conversão total

## 7. Estrutura de Pastas Criada

```
/js/tracker.js              <- Rastreamento do cliente
/api/track.php              <- Recebe eventos (NDJSON)
/api/webhook-pix.php        <- Recebe confirmação do Pix
/api/analytics.php          <- API de agregação de dados
/admin/dashboard.html       <- Dashboard visual
/data/logs/                 <- Arquivos NDJSON (gerados automaticamente)
```

## 8. Não Grava Dados Pessoais

O tracker NUNCA grava:
- Nome, CPF, Email, Telefone
- Endereço, CEP, Senha
- Qualquer informação pessoal

Grava SÓ:
- Qual etapa, qual evento
- Quanto tempo passou
- Se teve erro (mas não qual foi o erro específico)
- Dispositivo (mobile/desktop, navegador)
- Que abandonou a página

## 9. Dicas de Otimização

### Melhorar Taxa de Conversão
1. Teste a copy de cada botão (aumento, aprovação fácil, etc)
2. Veja qual etapa abandona mais
3. Reduza o número de campos obrigatórios

### Melhorar Velocidade
1. Lazy-load de imagens
2. Minificar CSS/JS
3. Gzip no servidor

### Validação Inline
Mostre erro em tempo real, não espere o submit:

```javascript
document.getElementById('email').addEventListener('blur', (e) => {
  const isValid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(e.target.value);
  Tracker.trackValidation('email', isValid);
  if (!isValid) {
    e.target.style.borderColor = '#e74c3c';
  }
});
```

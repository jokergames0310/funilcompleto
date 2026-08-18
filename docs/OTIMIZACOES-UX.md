# Otimizações de UX e Performance

## 1. Mobile-First: Checklist Rápido

Teste em um iPhone SE ou Pixel 5 (tamanho pequeno):

- [ ] Botões têm mínimo 44px de altura (touch target)
- [ ] Texto tem mínimo 12px de tamanho
- [ ] Campos de input têm padding confortável (12px)
- [ ] Não há scroll horizontal (overflow-x: hidden)
- [ ] Header não é muito grande (máximo 80px)
- [ ] Spacing responsivo (não fica colado nas laterais)

### Código de Proteção

```css
/* Evitar scroll horizontal */
* {
  max-width: 100%;
}

body {
  overflow-x: hidden;
}

/* Touch targets mínimos */
button, a {
  min-height: 44px;
  min-width: 44px;
}

/* Responsivo */
@media (max-width: 480px) {
  .container {
    padding: 16px;
    margin: 16px 0;
  }
  
  button {
    padding: 14px 16px; /* confortável pro polegar */
  }
}
```

## 2. Performance: Reduzir Tempo de Carregamento

### Lazy Loading de Imagens

```html
<!-- Antes -->
<img src="logo.png" alt="Logo">

<!-- Depois -->
<img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3C/svg%3E"
     data-src="logo.png"
     alt="Logo"
     loading="lazy">
```

### Minificar CSS inline

Se você tem estilos inline (muita gente faz), remova espaços:

```html
<!-- Antes: 150 bytes -->
<style>
  body {
    font-family: 'Inter', sans-serif;
    background: #EEEEEE;
  }
</style>

<!-- Depois: 60 bytes -->
<style>body{font-family:'Inter',sans-serif;background:#EEE}</style>
```

### Remover Scripts Desnecessários

Vira e mexe há scripts de terceiros carregando coisas que ninguém usa. Auditoria rápida:

```javascript
// Em F12 > Network > XHR
// Veja quais requests levam mais tempo
// Se for de terceiros com >200ms, considere remover ou lazy-load

// Lazy-load de scripts pesados:
window.addEventListener('DOMContentLoaded', () => {
  const script = document.createElement('script');
  script.src = 'heavy-analytics.js';
  script.async = true;
  document.body.appendChild(script);
});
```

## 3. Validação Inline: Feedback Imediato

Usuário odeia preencher formulário e só depois saber que errou.

### Email

```javascript
const emailInput = document.getElementById('email');

emailInput.addEventListener('blur', () => {
  const email = emailInput.value;
  const isValid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
  
  if (!isValid && email.length > 0) {
    emailInput.style.borderColor = '#e74c3c';
    emailInput.nextElementSibling.textContent = 'Email inválido';
    emailInput.nextElementSibling.style.display = 'block';
  } else {
    emailInput.style.borderColor = '#ddd';
    emailInput.nextElementSibling.style.display = 'none';
  }
});
```

### Telefone

```javascript
const phoneInput = document.getElementById('phone');

phoneInput.addEventListener('input', (e) => {
  let value = e.target.value.replace(/\D/g, '');
  value = value.replace(/(\d{2})(\d)/, '($1) $2');
  value = value.replace(/(\d{4})(\d)/, '$1-$2');
  e.target.value = value;
});

phoneInput.addEventListener('blur', () => {
  const phone = phoneInput.value.replace(/\D/g, '');
  const isValid = phone.length === 11; // (XX) XXXXX-XXXX
  
  if (!isValid && phone.length > 0) {
    phoneInput.style.borderColor = '#e74c3c';
  }
});
```

## 4. Animações: Menos É Mais

Animações pesadas matam FPS em celular lento. Regra:

- [ ] Máximo 2 animações por página
- [ ] Duração máxima 300ms
- [ ] Use `transform` e `opacity`, não `top`/`left`

```css
/* BOM - usa GPU */
@keyframes slideIn {
  from { transform: translateX(-100%); opacity: 0; }
  to { transform: translateX(0); opacity: 1; }
}

/* RUIM - não usa GPU, fica travado */
@keyframes badSlide {
  from { left: -100%; }
  to { left: 0; }
}
```

## 5. Cores: Contraste Mínimo

Seu design é bonito, mas algumas pessoas têm baixa visão. Regra WCAG:

- Texto sobre fundo deve ter razão de contraste mínima 4.5:1
- Elementos interativos (botões) 3:1

Teste em: https://webaim.org/resources/contrastchecker/

### Cores do Seu Projeto

```
Fundo (#EEEEEE) + Texto (#333333) = ✓ 10:1 (excelente)
Botão (#3483FA) + Texto white = ✓ 5:1 (bom)
Texto muted (#999999) + Fundo white = ✗ 3.5:1 (ruim, use #666666)
```

## 6. Copywriting: Urgência Moderada

Você já tem ROI, então mantenha o que funciona. Mas se quiser otimizar:

### Não Seja Genérico
```
❌ "Clique aqui"
✓ "Ver minha aprovação"
✓ "Ativar cartão agora"
```

### Não Force Urgência Falsa
```
❌ "OFERTA ACABA EM 5 MINUTOS!!!!!!" (mente)
✓ "Vaga disponível para seu perfil" (verdade)
```

### Remova Friction
```
❌ "Para continuar, você precisa de:"
✓ "Próxima etapa:"
```

## 7. Teste A/B Rápido

Teste botão vs. outro com o Tracker:

### Teste 1: Cor do Botão
```html
<!-- Versão A: azul (controle) -->
<button style="background: #3483FA;">Ver Aprovação</button>

<!-- Versão B: verde -->
<button style="background: #00A650;">Ver Aprovação</button>
```

```javascript
// No Tracker, capture qual versão
Tracker.track('button_variant', { color: 'blue' });
```

Depois veja no dashboard qual converteu mais.

## 8. Progressive Enhancement

Seu site funciona mesmo com JavaScript desabilitado?

### Teste
1. F12 > More tools > Rendering > Disable JavaScript
2. Recarregue a página
3. Dá pra navegar? Se sim, ótimo. Se não, você tem fallback pra fazer.

### Formulário Resiliente
```html
<!-- Sem JS: form traditional -->
<form action="/api/submit.php" method="POST">
  <input name="cpf" required>
  <button type="submit">Enviar</button>
</form>

<!-- Com JS: fetch + UX melhor -->
<script>
form.addEventListener('submit', (e) => {
  e.preventDefault();
  // ... fetch com validação inline
});
</script>
```

## 9. Otimizar para Redes Lentas

Teste em 3G:
1. F12 > Network > Throttling > Slow 3G
2. Recarregue
3. Página carrega em menos de 3 segundos?

Se não:
- Remova imagens grandes
- Lazy-load tudo que não é acima do fold
- Minifique CSS/JS

## 10. Checklist Final de UX

- [ ] Mobile: teste em um celular real, não só devtools
- [ ] Teclado: consegue navegar com Tab/Enter sem mouse?
- [ ] Acessibilidade: botões têm `aria-label` se for só ícone?
- [ ] Velocidade: LightHouse dá score mínimo 80?
- [ ] Erro gracioso: se API cair, mostra mensagem clara?
- [ ] Sem console errors: F12 > Console tem mensagens vermelhas?
- [ ] Links: todo link em novo guia tem `rel="noopener"`?
- [ ] Privacy: form não envia dados pra lugar errado?

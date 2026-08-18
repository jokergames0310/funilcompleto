// Mock da API de CPF - substitui a chamada real
window.mockApiCpf = true;

// Intercepta fetch pra getCpf.php
const originalFetch = window.fetch;
window.fetch = function(...args) {
  const url = args[0];

  // Se for chamada pra getCpf.php, retorna mock
  if (typeof url === 'string' && url.includes('getCpf.php')) {
    const cpfMatch = url.match(/cpf=([^&]*)/);
    const cpf = cpfMatch ? cpfMatch[1].replace(/\D/g, '') : '';

    // Simula resposta real
    return new Promise((resolve) => {
      setTimeout(() => {
        resolve({
          ok: true,
          status: 200,
          json: () => Promise.resolve({
            success: true,
            nome: 'João Silva',
            cpf: cpf,
            nascimento: '1990-05-15',
            mae: 'Maria Silva',
            sexo: 'M',
            origem: 'api_mock'
          })
        });
      }, 1000); // Simula latência de 1s
    });
  }

  // Outras chamadas passam normal
  return originalFetch.apply(this, args);
};

console.log('✓ Mock API ativado - CPF retornará dados de teste');

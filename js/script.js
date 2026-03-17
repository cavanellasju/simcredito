document.querySelectorAll('a[href^="#"]').forEach((anchor) => {
  anchor.addEventListener("click", function (e) {
    e.preventDefault();
    const target = document.querySelector(this.getAttribute("href"));
    if (target) {
      target.scrollIntoView({ behavior: "smooth" });
    }
  });
});

const menuOverlay = document.querySelector('.menu-overlay');
const menuClose = document.querySelector('.menu-close');
const menuLinks = document.querySelectorAll('.side-menu a'); /*Desenvolvido por Juliana Cavanellas*/
const menuToggle = document.querySelector('.menu-toggle');

function openMenu() {
  if (!menuOverlay || !menuToggle) return;
  menuOverlay.classList.add('active');
  menuOverlay.setAttribute('aria-hidden', 'false');
  menuToggle.setAttribute('aria-expanded', 'true');
  document.body.style.overflow = 'hidden';
}

function closeMenu() {
  if (!menuOverlay || !menuToggle) return;
  menuOverlay.classList.remove('active');
  menuOverlay.setAttribute('aria-hidden', 'true');
  menuToggle.setAttribute('aria-expanded', 'false');
  document.body.style.overflow = '';
}

if (menuToggle) {
  menuToggle.addEventListener('click', openMenu);
}

if (menuClose) {
  menuClose.addEventListener('click', closeMenu);
}

if (menuOverlay) {
  menuOverlay.addEventListener('click', (event) => {
    if (event.target === menuOverlay) {
      closeMenu();
    }
  });
}

menuLinks.forEach((link) => {
  link.addEventListener('click', closeMenu);
});

window.addEventListener('resize', () => {
  if (window.innerWidth > 768) {
    closeMenu();
  }
});

function exibirFeedback(box, mensagem, tipo = 'sucesso') {
  if (!box) return;

  box.hidden = false;
  box.textContent = mensagem;
  box.classList.toggle('erro', tipo === 'erro');
}

async function enviarFormularioAjax(formulario) {
  let resposta;
  try {
    resposta = await fetch(formulario.action, {
      method: 'POST',
      body: new FormData(formulario),
    });
  } catch (erro) {
    throw new Error('Não foi possível conectar ao servidor de envio.');
  }

  const tipoConteudo = resposta.headers.get('content-type') || '';
  if (!tipoConteudo.includes('application/json')) {
    throw new Error('Servidor de formulário indisponível neste ambiente. Use hospedagem com PHP.');
  }

  let dados = null;
  try {
    dados = await resposta.json();
  } catch (erro) {
    throw new Error('Resposta inválida do servidor.');
  }

  // Se recebeu uma resposta com conteúdo, considera sucesso
  if (dados && dados.mensagem) {
    return dados;
  }

  throw new Error('Resposta inválida do servidor.');
}

const formContato = document.getElementById('form-contato');
const btnSend = document.getElementById('btnSend');
const boxContato = document.getElementById('contato-feedback');

if (formContato && btnSend) {
  formContato.addEventListener('submit', async (event) => {
    event.preventDefault();

    if (!formContato.checkValidity()) {
      formContato.reportValidity();
      return;
    }

    btnSend.disabled = true;

    try {
      const resposta = await enviarFormularioAjax(formContato);
      btnSend.innerText = 'Mensagem enviada!';
      formContato.reset();
      exibirFeedback(boxContato, resposta.mensagem || 'Mensagem enviada com sucesso.');
    } catch (erro) {
      btnSend.disabled = false;
      exibirFeedback(boxContato, erro.message || 'Não foi possível enviar sua mensagem agora.', 'erro');
    }
  });
}

const formSimulacao = document.getElementById('form-simulacao');
const boxSimulacao = document.getElementById('simulacao-feedback');
const inputTelefone = formSimulacao?.querySelector('input[name="telefone"]');

if (inputTelefone) {
  inputTelefone.addEventListener('input', () => {
    const somenteNumeros = inputTelefone.value.replace(/\D/g, '').slice(0, 13);
    inputTelefone.value = somenteNumeros;
  });
}

if (formSimulacao) {
  formSimulacao.addEventListener('submit', async (event) => {
    event.preventDefault();

    if (!formSimulacao.checkValidity()) {
      formSimulacao.reportValidity();
      return;
    }

     try {
      const resposta = await enviarFormularioAjax(formSimulacao);
      formSimulacao.reset();
      exibirFeedback(boxSimulacao, resposta.mensagem || 'Recebemos sua solicitação e já está entrando em análise, aguarde nosso retorno!');
    } catch (erro) {
      exibirFeedback(boxSimulacao, erro.message || 'Não foi possível enviar a simulação agora.', 'erro');
    }
  });
}
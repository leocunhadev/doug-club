<?php

return [

    'navs' => [
        'start' => [
            ['view' => 'home', 'label' => 'Início'],
            ['view' => 'aulas', 'label' => 'Aulas'],
            ['view' => 'frameworks', 'label' => 'Frameworks'],
            ['view' => 'upgrade', 'label' => 'Sessão 1:1', 'lock' => true],
            ['view' => 'upgrade', 'label' => 'Meu cofre', 'lock' => true],
        ],
        'club' => [
            ['view' => 'home', 'label' => 'Início'],
            ['view' => 'aulas', 'label' => 'Aulas'],
            ['view' => 'cofre', 'label' => 'Meu cofre'],
            ['view' => 'agenda', 'label' => 'Minha sessão'],
            ['view' => 'pessoas', 'label' => 'Pessoas'],
            ['view' => 'encontros', 'label' => 'Encontros'],
            ['view' => 'frameworks', 'label' => 'Frameworks'],
        ],
        'mentor' => [
            ['view' => 'radar', 'label' => 'Radar'],
            ['view' => 'dossies', 'label' => 'Dossiês'],
            ['view' => 'conteudo', 'label' => 'Publicar'],
            ['view' => 'disp', 'label' => 'Disponibilidade'],
        ],
    ],

    'mq_words' => [
        'Tudo é gente',
        'Decisão Orientada',
        'Dado · Padrão · Decisão',
        'DOR: Direção, Orientação, Resultado',
        'Consumidor 4S',
        'Tudo é gente',
        'Decisão Orientada',
        'Dado · Padrão · Decisão',
        'DOR: Direção, Orientação, Resultado',
        'Consumidor 4S',
    ],

    'aulas' => [
        ['n' => '01', 't' => 'O comercial é gente', 'm' => 'Encontro gravado · Douglas · 58 min', 'cat' => 'Encontros', 'tier' => 'start'],
        ['n' => '02', 't' => 'Posicionamento que sustenta preço', 'm' => 'Convidado: Rodrigo Maciel · 64 min', 'cat' => 'Convidados', 'tier' => 'start'],
        ['n' => '03', 't' => 'Consumidor 4S na prática', 'm' => 'Framework em vídeo · Douglas · 41 min', 'cat' => 'Frameworks', 'tier' => 'start'],
        ['n' => '04', 't' => 'Decisão orientada por dados', 'm' => 'Encontro gravado · Douglas · 55 min', 'cat' => 'Encontros', 'tier' => 'start'],
        ['n' => '05', 't' => 'CAFÉ: prompts que decidem', 'm' => 'Framework em vídeo · Douglas · 38 min', 'cat' => 'Frameworks', 'tier' => 'start'],
        ['n' => '06', 't' => 'Precificação sem medo', 'm' => 'Convidada: Marina Prado · 62 min', 'cat' => 'Convidados', 'tier' => 'club'],
        ['n' => '07', 't' => 'Bastidores: sessão comentada', 'm' => 'Exclusivo CLUB · Douglas · 47 min', 'cat' => 'Encontros', 'tier' => 'club'],
    ],

    'frameworks' => [
        ['n' => '4S', 't' => 'Consumidor 4S', 'p' => 'Streaming, Search, Shop, Share. O mapa de como seu cliente decide antes de você aparecer.', 'aula' => 'Consumidor 4S na prática'],
        ['n' => 'DOR', 't' => 'Framework DOR', 'p' => 'Direção, Orientação, Resultado. A estrutura que transforma conversa de mentoria em plano executável.', 'aula' => 'Decisão orientada por dados'],
        ['n' => 'DPD', 't' => 'Dado, Padrão, Decisão', 'p' => 'O caminho de três passos que tira sua empresa do achismo e coloca no ciclo de decisão orientada.', 'aula' => 'Decisão orientada por dados'],
        ['n' => 'CAFÉ', 't' => 'Método CAFÉ', 'p' => 'O jeito DO de escrever prompts de IA que produzem decisão, não só texto bonito.', 'aula' => 'CAFÉ: prompts que decidem'],
    ],

    'dias' => [
        ['w' => 'seg', 'n' => '06', 'aberto' => false],
        ['w' => 'ter', 'n' => '07', 'aberto' => true],
        ['w' => 'qua', 'n' => '08', 'aberto' => false],
        ['w' => 'qui', 'n' => '09', 'aberto' => true],
        ['w' => 'sex', 'n' => '10', 'aberto' => true],
        ['w' => 'ter', 'n' => '14', 'aberto' => true],
        ['w' => 'qui', 'n' => '16', 'aberto' => true],
    ],

    'horarios' => [
        '07' => ['09h00', '10h30', '14h00'],
        '09' => ['10h00', '11h30', '15h00', '16h30'],
        '10' => ['09h00', '14h00'],
        '14' => ['10h00', '11h30', '14h00', '15h30'],
        '16' => ['09h00', '10h30'],
    ],

    'membros' => [
        ['ini' => 'RM', 'nome' => 'Ricardo Mendes', 'emp' => 'Mendes Log · Logística', 'bio' => 'Assumiu o comercial da própria empresa. Faturamento de R$ 8M/ano.', 'ensina' => ['Funil de indicação'], 'aprende' => ['Precificação', 'Discurso de venda']],
        ['ini' => 'MP', 'nome' => 'Marina Prado', 'emp' => 'Clínicas Vitalle · Saúde', 'bio' => 'Três unidades no Rio. Referência em precificação de serviços de saúde.', 'ensina' => ['Precificação', 'Expansão'], 'aprende' => ['Marca pessoal']],
        ['ini' => 'CF', 'nome' => 'Caio Fonseca', 'emp' => 'Grupo Andar · Imobiliário', 'bio' => 'Segunda geração assumindo a empresa da família, em plena virada digital.', 'ensina' => ['Negociação de alto valor'], 'aprende' => ['Funil de indicação']],
        ['ini' => 'AR', 'nome' => 'Alessandra Ribeiro', 'emp' => 'AR Odonto · Saúde', 'bio' => 'Primeira mentorada do CLUB. Reposicionando a clínica para o premium.', 'ensina' => ['Experiência do paciente'], 'aprende' => ['Oferta premium']],
    ],

    'encontros' => [
        ['d' => '15', 'm' => 'jul', 'tema' => 'Precificação sem medo', 'quem' => 'Convidada: Marina Prado', 'hora' => '19h', 'status' => 'next'],
        ['d' => '29', 'm' => 'jul', 'tema' => 'Decisão orientada por dados', 'quem' => 'Com Douglas', 'hora' => '19h', 'status' => 'fut'],
        ['d' => '17', 'm' => 'jun', 'tema' => 'O comercial é gente', 'quem' => 'Com Douglas', 'hora' => '19h', 'status' => 'past'],
    ],

    'dossies' => [
        'RM' => [
            'nome' => 'Ricardo Mendes',
            'emp' => 'Mendes Log',
            'desde' => 'membro desde mar/2026',
            'comp' => 'Gravar 3 conversas de venda até 09/jul',
            'fio' => [
                ['q' => '18 jun · Sessão 4', 't' => 'A virada do comercial', 'p' => 'Decidiu tirar o sócio da operação de venda e assumir o discurso. Percebeu que evitava vender por medo de rejeição, não por falta de tempo.'],
                ['q' => '21 mai · Sessão 3', 't' => 'Diagnóstico do funil', 'p' => '70% dos clientes vêm de indicação não estruturada. Definimos: indicação vira processo, não sorte.'],
                ['q' => '26 mar · Sessão 1', 't' => 'Escuta de DNA', 'p' => 'Fundou a empresa aos 24. Orgulho da operação, vergonha do comercial. O dono é o gargalo de crescimento.'],
            ],
        ],
        'AR' => [
            'nome' => 'Alessandra Ribeiro',
            'emp' => 'AR Odonto',
            'desde' => 'membro desde jan/2026',
            'comp' => 'Validar a oferta premium com 5 pacientes até 04/jul',
            'fio' => [
                ['q' => '12 jun · Sessão 6', 't' => 'ICP definido', 'p' => 'Paciente que valoriza estética e paga por experiência, não por procedimento. Ela valida com a base atual.'],
                ['q' => '15 mai · Sessão 5', 't' => 'A clínica premium', 'p' => 'Reposicionar: menos volume, mais valor. Reforma da recepção aprovada.'],
            ],
        ],
        'CF' => [
            'nome' => 'Caio Fonseca',
            'emp' => 'Grupo Andar',
            'desde' => 'membro desde abr/2026',
            'comp' => 'Sem compromisso ativo. Última sessão há 34 dias.',
            'fio' => [
                ['q' => '31 mai · Sessão 2', 't' => 'O peso do sobrenome', 'p' => 'Sente que precisa provar valor ao pai antes de mudar processos. Trabalhamos separar respeito de permissão.'],
            ],
        ],
    ],

    'blocos' => [
        ['dia' => 'Terças', 'h' => '09h às 12h', 'aberto' => true],
        ['dia' => 'Terças', 'h' => '14h às 17h', 'aberto' => false],
        ['dia' => 'Quintas', 'h' => '09h às 12h', 'aberto' => true],
        ['dia' => 'Quintas', 'h' => '14h às 17h', 'aberto' => true],
        ['dia' => 'Sextas', 'h' => '09h às 12h', 'aberto' => true],
        ['dia' => 'Sextas', 'h' => '14h às 17h', 'aberto' => false],
    ],

    'cofre_docs' => [
        ['t' => 'Insights da Sessão 4: a virada do comercial', 'm' => 'Enviado pelo Douglas · 19 jun · PDF', 'ic' => 'PDF', 'novo' => true],
        ['t' => 'Plano de indicação estruturada v1', 'm' => 'Construído na Sessão 3 · 22 mai · PDF', 'ic' => 'PDF', 'novo' => false],
        ['t' => 'Gravação da Sessão 4 (privada)', 'm' => 'Só você e o Douglas têm acesso · 18 jun', 'ic' => 'VÍDEO', 'novo' => false],
        ['t' => 'Tabela de preços revisada', 'm' => 'Compromisso da Sessão 2 · 25 abr · XLSX', 'ic' => 'XLSX', 'novo' => false],
        ['t' => 'Escuta de DNA: seu documento de partida', 'm' => 'Sessão 1 · 26 mar · PDF', 'ic' => 'PDF', 'novo' => false],
    ],

];

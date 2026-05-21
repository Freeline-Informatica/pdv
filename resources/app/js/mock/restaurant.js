const now = Date.now();

function minutesAgo(value) {
    return new Date(now - value * 60 * 1000).toISOString();
}

export const restaurantTables = [
    { id: 'T01', code: '01', waiterName: 'Ana', status: 'opened' },
    { id: 'T02', code: '02', waiterName: 'Bruno', status: 'opened' },
    { id: 'T03', code: '03', waiterName: 'Camila', status: 'closed' },
    { id: 'T04', code: '04', waiterName: 'Equipe', status: 'opened' },
    { id: 'BALCAO', code: 'Balcao', waiterName: 'Equipe', status: 'opened' },
];

export const restaurantCommands = [
    { id: 'C101', tableId: 'T01', code: '101', waiterName: 'Ana', status: 'opened' },
    { id: 'C102', tableId: 'T02', code: '102', waiterName: 'Bruno', status: 'opened' },
    { id: 'C103', tableId: 'T03', code: '103', waiterName: 'Camila', status: 'closed' },
    { id: 'C104', tableId: 'T04', code: '104', waiterName: 'Equipe', status: 'opened' },
    { id: 'B001', tableId: 'BALCAO', code: 'B001', waiterName: 'Equipe', status: 'opened' },
];

export const restaurantCategories = [
    { id: 'mais-pedidos', nome: 'Mais pedidos' },
    { id: 'entradas', nome: 'Entradas' },
    { id: 'hamburgueres', nome: 'Hamburgueres' },
    { id: 'pizzas', nome: 'Pizzas' },
    { id: 'sobremesas', nome: 'Sobremesas' },
    { id: 'bebidas', nome: 'Bebidas' },
];

export const restaurantProducts = [
    {
        id: 'P001',
        categoryId: 'hamburgueres',
        nome: 'Burger da Casa',
        descricao: 'Pao brioche, blend bovino 160g, queijo prato e maionese verde.',
        preco: 31.9,
        imagemUrl: '',
        estoque: 18,
        setores: ['cozinha'],
    },
    {
        id: 'P002',
        categoryId: 'hamburgueres',
        nome: 'Burger Bacon Crispy',
        descricao: 'Blend bovino, cheddar cremoso, bacon crocante e cebola caramelizada.',
        preco: 36.5,
        imagemUrl: '',
        estoque: 12,
        setores: ['cozinha'],
    },
    {
        id: 'P003',
        categoryId: 'pizzas',
        nome: 'Pizza Margherita Individual',
        descricao: 'Molho artesanal, muzzarela, tomate fresco e manjericao.',
        preco: 39.0,
        imagemUrl: '',
        estoque: 14,
        setores: ['cozinha'],
    },
    {
        id: 'P004',
        categoryId: 'entradas',
        nome: 'Batata Rustica',
        descricao: 'Porcao com molho da casa e toque de parmesao.',
        preco: 22.9,
        imagemUrl: '',
        estoque: 20,
        setores: ['cozinha'],
    },
    {
        id: 'P005',
        categoryId: 'sobremesas',
        nome: 'Brownie com Sorvete',
        descricao: 'Brownie de chocolate meio amargo com calda quente.',
        preco: 19.5,
        imagemUrl: '',
        estoque: 8,
        setores: ['cozinha'],
    },
    {
        id: 'P006',
        categoryId: 'bebidas',
        nome: 'Refrigerante Lata 350ml',
        descricao: 'Escolha o sabor no momento do pedido.',
        preco: 7.5,
        imagemUrl: '',
        estoque: 45,
        setores: ['bar'],
    },
    {
        id: 'P007',
        categoryId: 'bebidas',
        nome: 'Suco Natural 500ml',
        descricao: 'Laranja, limao ou abacaxi com hortela.',
        preco: 12.9,
        imagemUrl: '',
        estoque: 22,
        setores: ['bar'],
    },
    {
        id: 'P008',
        categoryId: 'bebidas',
        nome: 'Drink Sem Alcool Tropical',
        descricao: 'Mix de frutas com espuma citrica.',
        preco: 17.0,
        imagemUrl: '',
        estoque: 16,
        setores: ['bar'],
    },
    {
        id: 'P009',
        categoryId: 'mais-pedidos',
        nome: 'Combo Burger + Batata + Refri',
        descricao: 'O favorito da casa para uma refeicao completa.',
        preco: 42.9,
        imagemUrl: '',
        estoque: 9,
        setores: ['cozinha', 'bar'],
    },
    {
        id: 'P010',
        categoryId: 'entradas',
        nome: 'Dadinho de Tapioca',
        descricao: 'Porcao crocante com geleia agridoce.',
        preco: 24.0,
        imagemUrl: '',
        estoque: 0,
        setores: ['cozinha'],
    },
];

export const restaurantProductModifiers = {
    P001: {
        adicionais: [
            {
                id: 'a1',
                nome: 'Queijos',
                max: 2,
                obrigatorio: false,
                opcoes: [
                    { id: 'q-cheddar', nome: 'Cheddar', preco: 3.5 },
                    { id: 'q-prato', nome: 'Queijo prato', preco: 3.0 },
                    { id: 'q-gorgonzola', nome: 'Gorgonzola', preco: 4.2 },
                ],
            },
            {
                id: 'a2',
                nome: 'Extras',
                max: 3,
                obrigatorio: false,
                opcoes: [
                    { id: 'e-bacon', nome: 'Bacon crocante', preco: 5.0 },
                    { id: 'e-ovo', nome: 'Ovo frito', preco: 2.5 },
                    { id: 'e-picles', nome: 'Picles artesanal', preco: 1.8 },
                ],
            },
        ],
        removerIngredientes: ['Cebola roxa', 'Picles', 'Molho da casa'],
    },
    P003: {
        adicionais: [
            {
                id: 'p1',
                nome: 'Borda',
                max: 1,
                obrigatorio: false,
                opcoes: [
                    { id: 'b-catupiry', nome: 'Catupiry', preco: 6.0 },
                    { id: 'b-cheddar', nome: 'Cheddar', preco: 6.0 },
                ],
            },
        ],
        removerIngredientes: ['Manjericao', 'Tomate'],
    },
};

export const restaurantProductionTickets = [
    {
        id: 'TK-901',
        status: 'novo',
        setor: 'cozinha',
        mesa: '01',
        comanda: '101',
        garcom: 'Ana',
        criadoEm: minutesAgo(3),
        itens: [
            { id: 'i1', nome: 'Burger da Casa', quantidade: 2, observacao: 'Sem cebola' },
            { id: 'i2', nome: 'Batata Rustica', quantidade: 1, observacao: '' },
        ],
    },
    {
        id: 'TK-902',
        status: 'em_preparo',
        setor: 'bar',
        mesa: '02',
        comanda: '102',
        garcom: 'Bruno',
        criadoEm: minutesAgo(14),
        itens: [
            { id: 'i3', nome: 'Suco Natural 500ml', quantidade: 2, observacao: 'Sem acucar' },
            { id: 'i4', nome: 'Refrigerante Lata 350ml', quantidade: 1, observacao: '' },
        ],
    },
    {
        id: 'TK-903',
        status: 'pronto',
        setor: 'cozinha',
        mesa: '04',
        comanda: '104',
        garcom: 'Equipe',
        criadoEm: minutesAgo(21),
        itens: [
            { id: 'i5', nome: 'Pizza Margherita Individual', quantidade: 1, observacao: 'Sem tomate' },
        ],
    },
    {
        id: 'TK-904',
        status: 'entregue',
        setor: 'bar',
        mesa: '01',
        comanda: '101',
        garcom: 'Ana',
        criadoEm: minutesAgo(32),
        itens: [
            { id: 'i6', nome: 'Drink Sem Alcool Tropical', quantidade: 1, observacao: '' },
        ],
    },
];

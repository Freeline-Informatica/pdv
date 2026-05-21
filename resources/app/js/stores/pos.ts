import { computed, reactive } from 'vue';

export function usePosStore() {
    const cart = reactive<any[]>([]);

    const subtotal = computed(() => cart.reduce((acc, item) => acc + Number(item.preco_venda) * item.qty, 0));

    function addToCart(product: any) {
        const existing = cart.find((item) => item.id === product.id);
        if (existing) {
            existing.qty += 1;
            return;
        }

        cart.push({ ...product, qty: 1 });
    }

    function updateQty(item: any, delta: number) {
        item.qty += delta;
        if (item.qty <= 0) {
            const idx = cart.findIndex((current) => current.id === item.id);
            if (idx >= 0) cart.splice(idx, 1);
        }
    }

    return {
        cart,
        subtotal,
        addToCart,
        updateQty,
    };
}

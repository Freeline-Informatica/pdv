import { computed, ref } from 'vue';

const width = ref(typeof window !== 'undefined' ? window.innerWidth : 1440);

if (typeof window !== 'undefined') {
    window.addEventListener('resize', () => {
        width.value = window.innerWidth;
    });
}

export function useBreakpoints() {
    return {
        width,
        isDesktop: computed(() => width.value >= 1280),
        isTablet: computed(() => width.value >= 768 && width.value < 1280),
        isMobile: computed(() => width.value < 768),
    };
}

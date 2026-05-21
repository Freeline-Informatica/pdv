<template>
    <div class="app-shell">
        <slot name="topbar" />
        <div class="app-shell-main">
            <slot name="sidebar" />
            <slot />
        </div>
    </div>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <Link :href="'/'">
        <!-- Network GTC Logo -->
        <svg
            class="h-16 w-auto"
            viewBox="0 0 240 100"
            fill="none"
            xmlns="http://www.w3.org/2000/svg"
        >
            <!-- Wave/Arc element (left side) -->
            <path d="M 15 30 Q 10 35 15 50 Q 20 65 25 70 Q 18 75 12 70 Q 5 60 8 45 Q 10 35 18 25 Z"
                  fill="#003A70" />
            <path d="M 20 35 Q 18 40 20 52 Q 25 65 28 72 Q 33 70 30 58 Q 28 45 25 35 Z"
                  fill="#C41E3A" />

            <!-- Text: "Network" in blue -->
            <text x="45" y="55" font-family="Arial, sans-serif" font-size="32" font-weight="bold"
                  fill="#003A70" letter-spacing="0.5">
                Network
            </text>

            <!-- Text: "GTC" in red -->
            <text x="190" y="55" font-family="Arial, sans-serif" font-size="32" font-weight="bold"
                  fill="#C41E3A" letter-spacing="1">
                GTC
            </text>

            <!-- TM symbol (small circle) -->
            <circle cx="220" cy="20" r="3" fill="#003A70" />

            <!-- Tagline (optional, very small) -->
            <text x="45" y="80" font-family="Arial, sans-serif" font-size="8" fill="#666"
                  letter-spacing="0.3">
                LA 1ª RETE EUROPEA DI ALTA PROFESSIONALITÀ
            </text>
        </svg>
    </Link>
</template>

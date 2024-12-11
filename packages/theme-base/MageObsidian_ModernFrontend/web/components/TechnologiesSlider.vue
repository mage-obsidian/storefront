<template>
  <div
      class="shadow-lg slider-container relative w-full flex items-center justify-center bg-gradient-to-r from-blue-500 to-purple-600 p-6 rounded-lg overflow-hidden">
    <transition name="fade" mode="out-in">
      <div :key="currentIndex" class="slide flex flex-col items-center text-center space-y-4 w-full">
        <img :src="technologies[currentIndex].image" :alt="technologies[currentIndex].name"
             class="tech-image object-contain" height="300px" width="300px"/>
        <div
            class="text-container w-full p-4 rounded-lg shadow-lg backdrop-blur-md bg-gradient-to-r from-black/60 to-transparent">
          <h2 class="tech-name text-3xl font-extrabold text-white">{{ technologies[currentIndex].name }}</h2>
          <p class="tech-description text-lg text-white/90 mt-2">{{ technologies[currentIndex].description }}</p>
        </div>
      </div>
    </transition>
    <button @click="prevSlide"
            aria-label="Previous slide"
            class="nav-button prev absolute left-4 top-1/2 transform -translate-y-1/2 bg-white/90 text-gray-800 p-3 rounded-full shadow-md hover:bg-white transition duration-300 flex items-center justify-center">
      <ChevronLeftIcon class="h-6 w-6 text-gray-800"/>
    </button>
    <button @click="nextSlide"
            aria-label="Next slide"
            class="nav-button next absolute right-4 top-1/2 transform -translate-y-1/2 bg-white/90 text-gray-800 p-3 rounded-full shadow-md hover:bg-white transition duration-300 flex items-center justify-center">
      <ChevronRightIcon class="h-6 w-6 text-gray-800"/>
    </button>
  </div>
</template>

<script setup>
import {ref, onMounted, onUnmounted} from 'vue';
import {ChevronLeftIcon, ChevronRightIcon} from '#/@heroicons/vue/24/outline';
import viteLogo from 'MageObsidian_ModernFrontend::assets/vite_logo.png';
import tailwindLogo from 'MageObsidian_ModernFrontend::assets/tailwind_logo.png';
import vueLogo from 'MageObsidian_ModernFrontend::assets/vue_logo.png';
import magentoLogo from 'MageObsidian_ModernFrontend::assets/magento_logo.png';

const currentIndex = ref(0);
const technologies = [
  {
    name: 'Vite',
    image: viteLogo,
    description: 'Vite is a fast development bundler that enhances the developer experience.',
  },
  {
    name: 'Tailwind CSS',
    image: tailwindLogo,
    description: 'Tailwind CSS is a utility-first CSS framework for creating modern, responsive designs.',
  },
  {
    name: 'Vue.js',
    image: vueLogo,
    description: 'Vue.js is a progressive JavaScript framework for building interactive user interfaces.',
  },
  {
    name: 'Magento',
    image: magentoLogo,
    description: 'Magento is an open-source e-commerce platform offering flexibility and control over online store design and functionality.',
  },
];

const nextSlide = () => {
  currentIndex.value = (currentIndex.value + 1) % technologies.length;
};

const prevSlide = () => {
  currentIndex.value = (currentIndex.value - 1 + technologies.length) % technologies.length;
};

let slideInterval;

onMounted(() => {
  slideInterval = setInterval(nextSlide, 10000);
});

onUnmounted(() => {
  clearInterval(slideInterval);
});
</script>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.5s ease-in-out;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}

.text-container {
  text-shadow: 0 2px 4px rgba(0, 0, 0, 0.4);
}
</style>

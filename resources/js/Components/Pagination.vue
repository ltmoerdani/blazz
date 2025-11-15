<template>
    <div class="inline-flex justify-center items-center">
      <div v-if="hasPages" class="hidden mr-2 text-sm text-gray-600 lg:block">{{ total }} items</div>

      <div class="flex space-x-1 items-top" v-if="hasPages">
        <button
            :disabled="noPreviousPage"
            :class="{'opacity-50': noPreviousPage}"
            @click="loadPage(1)"
            class="inline-flex justify-center items-center w-11 h-11 text-gray-700 bg-white rounded border border-gray-200 shadow-sm outline-none hover:bg-gray-50 lg:h-9 lg:w-9 lg:text-sm focus:ring-1 focus:ring-blue-500 focus:border-blue-500">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 lg:h-3 lg:w-3" fill="none" viewBox="0 0 24 24"
               stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 19l-7-7 7-7m8 14l-7-7 7-7"/>
          </svg>
        </button>
        <button
            :disabled="noPreviousPage"
            :class="{'opacity-50': noPreviousPage}"
            @click="loadPage(currentPage - 1)"
            class="inline-flex justify-center items-center w-11 h-11 text-gray-700 bg-white rounded border border-gray-200 shadow-sm outline-none hover:bg-gray-50 lg:h-9 lg:w-9 focus:ring-1 focus:ring-blue-500 focus:border-blue-500">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 lg:h-3 lg:w-3" fill="none" viewBox="0 0 24 24"
               stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
          </svg>
        </button>

        <div class="flex flex-row items-center space-x-1">
          <div class="pl-2 text-gray-600 lg:text-sm">{{ page }}</div>
          <!---<input type="number" @keydown.enter="loadPage(page)" v-model="page" class="px-2 w-11 h-11 text-center rounded border border-gray-400 shadow-sm lg:h-9 lg:w-9 lg:text-sm focus:ring-blue-500 focus:border-blue-500"/>-->
          <div class="pr-2 text-gray-600 lg:text-sm">of {{ lastPage }}</div>
        </div>

        <button
            :disabled="noNextPage"
            :class="{'opacity-50': noNextPage}"
            @click="loadPage(currentPage + 1)"
            class="inline-flex justify-center items-center w-11 h-11 text-gray-700 bg-white rounded border border-gray-300 shadow-sm outline-none hover:bg-gray-50 lg:h-9 lg:w-9 focus:ring-1 focus:ring-blue-500 focus:border-blue-500">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 lg:h-3 lg:w-3" fill="none" viewBox="0 0 24 24"
               stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
          </svg>
        </button>

        <button
            :disabled="noNextPage"
            :class="{'opacity-50': noNextPage}"
            @click="loadPage(lastPage)"
            class="inline-flex justify-center items-center w-11 h-11 text-gray-700 bg-white rounded border border-gray-300 shadow-sm outline-none hover:bg-gray-50 lg:h-9 lg:w-9 focus:ring-1 focus:ring-blue-500 focus:border-blue-500">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 lg:h-3 lg:w-3" fill="none" viewBox="0 0 24 24"
               stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 5l7 7-7 7M5 5l7 7-7 7"/>
          </svg>
        </button>
      </div>
    </div>
  </template>
  
  <script>
  export default {
    name: 'Pagination',
    props: {
      pagination: Object,
    },
    data() {
      return {
        page: (this.pagination && this.pagination.current_page) ? this.pagination.current_page : 1
      }
    },
    watch: {
      'pagination.current_page': function(page) {
        if (page) this.page = page;
      },
      pagination: {
        handler(newPagination) {
          if (newPagination && newPagination.current_page) {
            this.page = newPagination.current_page;
          }
        },
        deep: true,
        immediate: true
      }
    },
    methods: {
      loadPage(page) {
        this.$inertia.get(this.$page.url, {page: page}, {
          preserveState: true
        });
      }
    },
    computed: {
      noPreviousPage() {
        return !this.pagination || !this.pagination.current_page || this.pagination.current_page <= 1;
      },
      noNextPage() {
        return !this.pagination || !this.pagination.last_page ||
               (this.pagination.current_page + 1 > this.pagination.last_page);
      },
      hasPages() {
        return this.pagination && this.pagination.last_page > 1;
      },
      currentPage() {
        return this.pagination ? this.pagination.current_page : 1;
      },
      lastPage() {
        return this.pagination ? this.pagination.last_page : 1;
      },
      total() {
        return this.pagination ? this.pagination.total : 0;
      }
    }
  };
  </script>
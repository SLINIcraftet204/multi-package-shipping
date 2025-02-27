<template>
  <sw-card title="Multi Package Shipping Einstellungen">
    <sw-container>
      <sw-field
          label="Maximales Paketgewicht (kg)"
          type="number"
          v-model="maxPackageWeight"
          step="0.1"
          @input="saveConfig"
      />
    </sw-container>
  </sw-card>
</template>

<script>
export default {
  data() {
    return {
      maxPackageWeight: 31.5
    };
  },
  created() {
    this.loadConfig();
  },
  methods: {
    async loadConfig() {
      const response = await this.$http.get('/api/_action/system-config/MultiPackageShipping.config.maxPackageWeight');
      this.maxPackageWeight = response.data || 31.5;
    },
    async saveConfig() {
      await this.$http.post('/api/_action/system-config', {
        "MultiPackageShipping.config.maxPackageWeight": this.maxPackageWeight
      });
    }
  }
};
</script>

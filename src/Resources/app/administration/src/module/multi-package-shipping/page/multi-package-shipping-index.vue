<script>
export default {
  name: "multi-package-shipping-index",
  data() {
    return {
      maxPackageWeight: 31.5
    };
  }
}
</script>

<template>
  <sw-page>
    <h1>Multi Package Shipping Einstellungen</h1>
    <sw-card>
      <sw-text-field v-model="maxPackageWeight"
                     label="Maximales Paketgewicht (kg)"
                     type="number" />
    </sw-card>
  </sw-page>
</template>

<style scoped>

</style>
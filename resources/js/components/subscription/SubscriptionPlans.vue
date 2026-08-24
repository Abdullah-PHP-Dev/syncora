<template>
  <div class="subscription-page">
    <div class="container">
      <div class="subscription-header">
        <div class="eyebrow"><i class="bi bi-stars"></i> Simple & Flexible Pricing</div>
        <h1>Choose the plan that <span>fits your business</span></h1>
        <p>Start small, scale when you need and switch your billing cycle anytime. Choose the plan that gives your business everything it needs to grow.</p>
      </div>

      <div v-if="currentSubscription" class="current-subscription">
        <div class="current-subscription-content">
          <div class="current-plan">
            <div class="current-plan-icon"><i class="bi bi-box-seam"></i></div>
            <div>
              <div class="current-plan-label">Your Current Subscription</div>
              <div class="current-plan-name">{{ currentSubscription.bundle_name }}</div>
            </div>
          </div>
          <div class="subscription-dates">
            <div class="date-item">
              <span class="date-label">Started</span>
              <span class="date-value">{{ formatDate(currentSubscription.start_date) }}</span>
            </div>
            <div class="date-item">
              <span class="date-label">Renews / Ends</span>
              <span class="date-value">{{ formatDate(currentSubscription.end_date) }}</span>
            </div>
            <span class="subscription-status">{{ currentSubscription.status }}</span>
          </div>
        </div>
      </div>

      <div class="billing-section">
        <div class="billing-title">Choose your billing cycle</div>
        <div class="billing-wrapper">
          <div class="billing-container">
            <div class="billing-option">
              <input v-model="billingCycle" type="radio" id="billing_monthly" value="monthly">
              <label for="billing_monthly"><i class="bi bi-calendar3 mr-1"></i> Monthly</label>
            </div>
            <div class="billing-option">
              <input v-model="billingCycle" type="radio" id="billing_yearly" value="yearly">
              <label for="billing_yearly">
                <i class="bi bi-calendar-check mr-1"></i> Yearly
                <span class="billing-save">BEST VALUE</span>
              </label>
            </div>
          </div>
        </div>
      </div>

      <div v-if="loading" class="text-center py-5">
        <i class="bi bi-arrow-repeat fa-spin"></i> Loading plans...
      </div>

      <div v-else class="row">
        <div v-for="(packageItem,index) in packages" :key="packageItem.id" class="col-lg-4 col-md-6 mb-4">
          <div :class="['package-card',{'current':packageItem.is_current,'recommended':packageItem.is_recommended && !packageItem.is_current}]">
            <div v-if="packageItem.is_current" class="plan-badge current-badge">
              <i class="bi bi-check-circle"></i> Current Plan
            </div>
            <div v-else-if="packageItem.is_recommended" class="plan-badge popular-badge">
              <i class="bi bi-stars"></i> Most Popular
            </div>

            <div class="package-icon">
              <i v-if="index === 0" class="bi bi-rocket"></i>
              <i v-else-if="index === 1" class="bi bi-stars"></i>
              <i v-else class="bi bi-building"></i>
            </div>

            <div class="package-name">{{ packageItem.name }}</div>
            <div class="package-description">{{ packageItem.description }}</div>

            <div class="package-price">
              <strong class="package-price-value">{{ formatPrice(getPrice(packageItem)) }}</strong>
              <small>{{ packageItem.currency }}</small>
              <span class="period">/ {{ billingCycle === 'yearly' ? 'year' : 'month' }}</span>
            </div>

            <div class="annual-note" :style="{visibility:billingCycle === 'yearly' ? 'visible' : 'hidden'}">
              <i class="bi bi-check-circle-fill mr-1"></i> Save with annual billing
            </div>

            <div class="package-features">
              <div class="feature-heading">What's included</div>

              <div
                  v-for="(feature,index) in getPackageFeatures(packageItem)"
                  :key="index"
                  class="package-feature"
              >
                <i class="bi bi-check"></i>
                <span>{{ feature }}</span>
              </div>

              <div v-if="!getPackageFeatures(packageItem).length" class="package-feature">
                <i class="bi bi-check"></i>
                <span>Everything you need to grow your business</span>
              </div>
            </div>

            <a :href="getCheckoutUrl(packageItem)" class="btn-select-plan">
              <template v-if="packageItem.is_current">
                <i class="bi bi-check-circle"></i>
                <span>Continue with Current Plan</span>
                <i class="bi bi-arrow-right"></i>
              </template>
              <template v-else>
                <span>Continue with this plan</span>
                <i class="bi bi-arrow-right"></i>
              </template>
            </a>
          </div>
        </div>
      </div>

      <div class="subscription-trust">
        <span class="trust-item"><i class="bi bi-shield-check"></i> Secure checkout</span>
        <span class="trust-item"><i class="bi bi-credit-card"></i> Multiple payment options</span>
        <span class="trust-item"><i class="bi bi-arrow-repeat"></i> Flexible billing</span>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name:'SubscriptionPlans',

  data(){
    return {
      loading:false,
      packages:[],
      currentSubscription:null,
      billingCycle:'monthly'
    }
  },

  mounted(){
    this.loadPlans()
  },

  methods: {
    loadPlans() {
      this.loading = true

      axios.get('/admin/subscription/plans')
          .then(response => {
            const data = response.data.data || {}
            this.packages = data.packages || []
            this.currentSubscription = data.current_subscription || null
          })
          .catch(error => {
            console.error('Unable to load subscription plans', error)
          })
          .finally(() => {
            this.loading = false
          })
    },

    getPrice(packageItem) {
      return this.billingCycle === 'yearly'
          ? packageItem.yearly.price
          : packageItem.monthly.price
    },

    getPackageFeatures(packageItem) {
      const features = packageItem.features || {}
      const limits = features.limits || {}
      const enabledFeatures = features.features || {}
      const result = []

      Object.keys(limits).forEach(key => {
        const value = limits[key]

        if (value !== null && value !== undefined) {
          result.push(this.formatLimitFeature(key, value))
        }
      })

      Object.keys(enabledFeatures).forEach(key => {
        if (enabledFeatures[key] === true) {
          result.push(this.formatFeatureName(key))
        }
      })

      return result
    },

    formatLimitFeature(key, value) {
      const name = this.formatFeatureName(key)

      if (Number(value) === -1) {
        return `Unlimited ${name}`
      }

      if (key === 'storage_gb') {
        return `${Number(value).toLocaleString()} GB ${name}`
      }

      return `${Number(value).toLocaleString()} ${name}`
    },

    formatFeatureName(key) {
      const names = {
        users: 'users',
        products: 'products',
        storage_gb: 'storage',
        orders: 'orders',
        sellers: 'sellers',
        stores: 'stores',
        staff: 'staff',
        warehouses: 'warehouses',
        ads_manager: 'Ads Manager',
        analytics: 'Analytics',
        inventory_management: 'Inventory Management',
        advanced_reports: 'Advanced Reports',
        api_access: 'API Access',
        order_management: 'Order Management',
        customer_management: 'Customer Management',
        shipping: 'Shipping',
        integrations: 'Integrations',
        marketplace: 'Marketplace',
        automation: 'Automation',
        bulk_import: 'Bulk Import',
        bulk_export: 'Bulk Export',
        notifications: 'Notifications',
        support: 'Priority Support'
      }

      if (names[key]) {
        return names[key]
      }

      return key
          .replace(/_/g, ' ')
          .replace(/\b\w/g, char => char.toUpperCase())
    },

    formatPrice(price) {
      return Number(price).toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
      })
    },

    formatDate(date) {
      if (!date) {
        return '-'
      }

      return new Date(date).toLocaleDateString('en-GB', {
        day: '2-digit',
        month: 'short',
        year: 'numeric'
      })
    },

    getCheckoutUrl(packageItem) {
      const planId = this.billingCycle === 'yearly'
          ? packageItem.yearly.plan_id
          : packageItem.monthly.plan_id

      return `${packageItem.checkout_url}?plan_id=${encodeURIComponent(planId)}&cycle=${this.billingCycle}`
    }
  }
}
</script>
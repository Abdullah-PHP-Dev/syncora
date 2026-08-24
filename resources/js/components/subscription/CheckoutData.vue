<template>
  <div class="checkout-page">
    <div class="container">
      <div class="checkout-header">
        <div class="checkout-header-row">
          <div class="checkout-header-content">
            <h1>Complete your subscription</h1>
            <p>Choose your billing cycle and payment method to activate <strong>{{ packageData.name }}</strong>.</p>
          </div>
          <a href="javascript:void(0)" class="back-to-subscription" @click="goBack">
            <i class="bi bi-arrow-left"></i> Back to Subscription Plans
          </a>
        </div>
      </div>

      <div v-if="loading" class="text-center py-5">
        <i class="bi bi-arrow-repeat fa-spin"></i> Loading plan...
      </div>

      <div v-else-if="!packageData.id" class="text-center py-5">
        <i class="bi bi-exclamation-circle"></i>
        Unable to load subscription plan.
      </div>

      <form v-else @submit.prevent="submitForm">
        <div class="row">
          <div class="col-lg-7">
            <div class="checkout-card">
              <div class="checkout-section">
                <div class="section-title">
                  <h4>Choose your billing cycle</h4>
                </div>
                <p class="section-description">Select how frequently you want to be billed.</p>

                <div class="billing-toggle">
                  <div class="billing-option">
                    <input v-model="billingCycle" type="radio" id="monthly" value="monthly">
                    <label for="monthly">Monthly</label>
                  </div>
                  <div class="billing-option">
                    <input v-model="billingCycle" type="radio" id="yearly" value="yearly">
                    <label for="yearly">
                      Yearly
                      <span class="save-badge">BEST VALUE</span>
                    </label>
                  </div>
                </div>
              </div>

              <div class="checkout-section">
                <div class="section-title">
                  <h4>Payment method</h4>
                </div>
                <p class="section-description">Select your preferred payment method.</p>

                <div class="payment-grid">
                  <div class="payment-option">
                    <input v-model="paymentMethod" type="radio" id="payment_card" value="card">
                    <label for="payment_card">
                      <span class="payment-check"></span>
                      <div class="payment-icon card-logo">
                        <img :src="cardImage" alt="Visa and Mastercard">
                      </div>
                      <span class="payment-description">Visa, Mastercard</span>
                    </label>
                  </div>

                  <div class="payment-option d-none">
                    <input v-model="paymentMethod" type="radio" id="payment_tabby" value="tabby">
                    <label for="payment_tabby">
                      <span class="payment-check"></span>
                      <div class="payment-icon tabby-logo">
                        <img :src="tabbyImage" alt="Tabby">
                      </div>
                      <span class="payment-description">Pay in installments</span>
                    </label>
                  </div>

                  <div class="payment-option">
                    <input v-model="paymentMethod" type="radio" id="payment_tamara" value="tamara">
                    <label for="payment_tamara">
                      <span class="payment-check"></span>
                      <div class="payment-icon tamara-logo">
                        <img :src="tamaraImage" alt="Tamara">
                      </div>
                      <span class="payment-description">Split your payment</span>
                    </label>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="col-lg-5">
            <div class="summary-card">
              <div class="summary-top">
                <div class="summary-label">Your Plan</div>
                <h3>{{ packageData.name }}</h3>
                <p>Subscription plan</p>

                <div class="selected-plan-info">
                  <span>Selected Plan ID</span>
                  <strong>{{ selectedPlanId }}</strong>
                </div>
              </div>

              <div class="summary-body">
                <div class="summary-billing">
                  <span>Billing cycle</span>
                  <strong>{{ billingCycle === 'yearly' ? 'Yearly' : 'Monthly' }}</strong>
                </div>

                <div class="summary-row">
                  <span class="label">Subscription</span>
                  <span class="value">{{ formatPrice(currentPrice) }} {{ currency }}</span>
                </div>

                <div v-if="discount > 0" class="summary-row">
                  <span class="label">Coupon discount</span>
                  <span class="value discount-value">-{{ formatPrice(discount) }} {{ currency }}</span>
                </div>

                <div class="summary-row">
                  <span class="label">VAT</span>
                  <span class="value">{{ formatPrice(vat) }} {{ currency }}</span>
                </div>

                <div class="coupon-box">
                  <div class="coupon-title">
                    <i class="bi bi-tag"></i> Have a coupon?
                  </div>

                  <div class="coupon-input-group">
                    <input v-model="couponInput" type="text" class="coupon-input" placeholder="Enter coupon code" :disabled="couponLoading || processing">

                    <button type="button" class="btn-coupon" @click="applyCoupon" :disabled="couponLoading || processing">
                      {{ couponLoading ? 'Checking...' : 'Apply' }}
                    </button>
                  </div>

                  <div v-if="couponMessage" class="coupon-message" :class="couponValid ? 'text-success' : 'text-danger'">
                    {{ couponMessage }}
                  </div>
                </div>

                <div class="mt-4">
                  <div class="features-title">Included with this plan</div>

                  <div v-for="(feature,index) in packageFeatures" :key="index" class="feature-item">
                    <i class="bi bi-check-circle-fill"></i>
                    {{ feature }}
                  </div>
                </div>

                <div class="summary-total">
                  <span>Total due today</span>
                  <div class="total-price">
                    <strong>
                      {{ formatPrice(total) }}
                      <small>{{ currency }}</small>
                    </strong>
                    <small>{{ billingCycle === 'yearly' ? 'billed yearly' : 'billed monthly' }}</small>
                  </div>
                </div>

                <div class="mt-4">
                  <button type="submit" class="btn btn-activate" :disabled="processing">
                    <i class="bi bi-lock-fill mr-1"></i>
                    {{ processing ? 'Processing...' : 'Continue to Payment' }}
                  </button>
                </div>

                <div class="secure-payment">
                  <i class="bi bi-shield-check"></i>
                  Secure payment · Your information is protected
                </div>

                <div v-if="errorMessage" class="alert alert-danger mt-3 mb-0">
                  {{ errorMessage }}
                </div>
              </div>
            </div>
          </div>
        </div>
      </form>
    </div>
  </div>
</template>

<script>
export default {
  name:'SubscriptionCheckout',
  props:{
    bundleId:{type:[String,Number],required:true},
    cycle:{type:String,default:'monthly'},
    checkoutUrl:{type:String,required:true},
    couponUrl:{type:String,required:true},
    csrfToken:{type:String,required:true},
    cardImage:{type:String,default:''},
    tabbyImage:{type:String,default:''},
    tamaraImage:{type:String,default:''}
  },
  data(){
    return {
      loading:false,
      processing:false,
      packageData:{},
      billingCycle:['monthly','yearly'].includes(this.cycle)?this.cycle:'monthly',
      paymentMethod:'card',
      couponInput:'',
      couponCode:'',
      couponMessage:'',
      couponValid:false,
      couponLoading:false,
      discount:0,
      vat:0,
      errorMessage:''
    }
  },
  computed:{
    currency(){
      return this.packageData.currency || 'SAR'
    },
    selectedPlan(){
      return this.billingCycle === 'yearly'
          ? this.packageData.yearly
          : this.packageData.monthly
    },
    selectedPlanId(){
      return this.selectedPlan ? this.selectedPlan.plan_id : this.bundleId
    },
    currentPrice(){
      return this.selectedPlan ? Number(this.selectedPlan.price || 0) : 0
    },
    total(){
      return Math.max(this.currentPrice - Number(this.discount || 0) + Number(this.vat || 0),0)
    },
    packageFeatures(){
      const features=this.packageData.features || {}
      const result=[]
      const limits=features.limits || {}
      const enabled=features.features || {}

      Object.keys(limits).forEach(key=>{
        if(limits[key] !== null && limits[key] !== undefined){
          result.push(this.formatLimitFeature(key,limits[key]))
        }
      })

      Object.keys(enabled).forEach(key=>{
        if(enabled[key] === true){
          result.push(this.formatFeatureName(key))
        }
      })

      return result
    }
  },
  watch:{
    billingCycle(){
      this.discount=0
      this.couponCode=''
      this.couponInput=''
      this.couponMessage=''
      this.couponValid=false
      this.errorMessage=''
    },
    paymentMethod(){
      this.errorMessage=''
    }
  },
  mounted(){
    this.loadPackage()
  },
  methods: {
    loadPackage() {
      this.loading = true
      this.errorMessage = ''

      axios.get('/admin/subscription/plans')
          .then(response => {
            const packages = response.data.data.packages || []

            this.packageData = packages.find(item => String(item.id) === String(this.bundleId)) || {}

            if (!this.packageData.id) {
              this.errorMessage = 'Subscription plan not found.'
            }
          })
          .catch(error => {
            console.error('Unable to load subscription plan', error)
            this.errorMessage = error.response && error.response.data && error.response.data.message
                ? error.response.data.message
                : 'Unable to load subscription plan.'
          })
          .finally(() => {
            this.loading = false
          })
    },

    applyCoupon() {
      const coupon = this.couponInput.trim()

      if (!coupon) {
        this.couponMessage = 'Please enter a coupon code.'
        this.couponValid = false
        return
      }

      this.couponLoading = true
      this.couponMessage = ''
      this.errorMessage = ''

      axios.post(this.couponUrl, {
        coupon_code: coupon,
        bundle_id: this.bundleId,
        plan_id: this.selectedPlanId,
        cycle: this.billingCycle,
        amount: this.currentPrice
      }, {
        headers: {'X-CSRF-TOKEN': this.csrfToken}
      })
          .then(response => {
            const data = response.data

            if (data.success) {
              this.discount = Number(data.discount || 0)
              this.couponCode = coupon
              this.couponValid = true
              this.couponMessage = data.message || 'Coupon applied successfully.'
            } else {
              this.discount = 0
              this.couponCode = ''
              this.couponValid = false
              this.couponMessage = data.message || 'Invalid coupon code.'
            }
          })
          .catch(error => {
            this.discount = 0
            this.couponCode = ''
            this.couponValid = false
            this.couponMessage = error.response && error.response.data && error.response.data.message
                ? error.response.data.message
                : 'Unable to validate coupon.'
          })
          .finally(() => {
            this.couponLoading = false
          })
    },

    submitForm() {
      if (this.processing) return

      if (!this.bundleId) {
        this.errorMessage = 'Invalid subscription plan.'
        return
      }

      if (!this.selectedPlanId) {
        this.errorMessage = 'Invalid payment plan.'
        return
      }

      if (!this.paymentMethod) {
        this.errorMessage = 'Please select a payment method.'
        return
      }

      this.processing = true
      this.errorMessage = ''

      axios.post(this.checkoutUrl, {
        bundle_id: this.bundleId,
        plan_id: this.selectedPlanId,
        cycle: this.billingCycle,
        payment_method: this.paymentMethod,
        coupon_code: this.couponCode || null
      }, {
        headers: {
          'X-CSRF-TOKEN': this.csrfToken,
          'Accept': 'application/json'
        }
      })
          .then(response => {
            const data = response.data

            if (data.success && data.checkout_url) {
              window.location.href = data.checkout_url
              return
            }

            if (data.success && data.redirect_url) {
              window.location.href = data.redirect_url
              return
            }

            this.errorMessage = data.message || 'Unable to create payment checkout.'
            this.processing = false
          })
          .catch(error => {
            console.error('Subscription checkout error', error)

            this.errorMessage = error.response && error.response.data && error.response.data.message
                ? error.response.data.message
                : 'Unable to process your subscription. Please try again.'

            this.processing = false
          })
    },

    formatLimitFeature(key, value) {
      const labels = {
        users: 'team members',
        products: 'products',
        storage_gb: 'storage',
        orders: 'orders'
      }

      const label = labels[key] || this.formatFeatureName(key)

      if (Number(value) === -1) {
        return `Unlimited ${label}`
      }

      if (key === 'storage_gb') {
        return `${value} GB ${label}`
      }

      return `${Number(value).toLocaleString()} ${label}`
    },

    formatFeatureName(key) {
      const labels = {
        ads_manager: 'Ads Manager',
        analytics: 'Analytics',
        inventory_management: 'Inventory Management',
        advanced_reports: 'Advanced Reports',
        api_access: 'API Access',
        order_management: 'Order Management',
        customer_management: 'Customer Management',
        shipping: 'Shipping',
        integrations: 'Integrations'
      }

      return labels[key] || key.replace(/_/g, ' ').replace(/\b\w/g, char => char.toUpperCase())
    },

    formatPrice(value) {
      return Number(value || 0).toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
      })
    },

    goBack() {
      window.history.back()
    }
  }
}
</script>
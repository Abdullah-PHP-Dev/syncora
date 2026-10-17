<template>
  <div class="sc-page">
    <a class="sc-back" :href="backUrl"><i class="bi bi-arrow-left" aria-hidden="true"></i>{{ t('back') }}</a>
    <header class="sc-header"><div><span class="sc-eyebrow">{{ t('eyebrow') }}</span><h1>{{ t('heading') }}</h1><p>{{ t('intro') }}</p></div><span class="sc-secure-badge"><i class="bi bi-shield-check" aria-hidden="true"></i>{{ t('secure') }}</span></header>

    <div v-if="loading" class="sc-state" role="status"><span class="sc-spinner"></span><p>{{ t('loading') }}</p></div>
    <div v-else-if="!packageData.id" class="sc-state"><span class="sc-feature-icon"><i class="bi bi-box" aria-hidden="true"></i></span><h2>{{ t('load_error') }}</h2><button type="button" class="sc-button sc-button-primary" @click="loadPackage">{{ t('retry') }}</button></div>

    <form v-else class="sc-layout" @submit.prevent="submitForm">
      <div class="sc-main">
        <section class="sc-plan sc-panel"><div class="sc-plan-mark"><i class="bi bi-box-seam" aria-hidden="true"></i></div><div class="sc-plan-description"><span class="sc-small-label">{{ t('selected_plan') }}</span><h2>{{ packageData.name }}</h2><p>{{ packageData.description }}</p></div><div class="sc-plan-price"><strong>{{ formatPrice(currentPrice) }} <small>{{ currency }}</small></strong><span>{{ t(billingCycle === 'yearly' ? 'per_year' : 'per_month') }}</span><small>{{ t('vat_included') }}</small></div></section>

        <section class="sc-panel sc-section"><div class="sc-section-header"><span class="sc-step">01</span><div><h2 id="sc-billing-heading">{{ t('billing_title') }}</h2><p>{{ t('billing_help') }}</p></div></div>
          <fieldset class="sc-choice-grid" aria-labelledby="sc-billing-heading"><div v-for="option in ['monthly', 'yearly']" :key="option" class="sc-choice"><input v-model="billingCycle" :disabled="processing" type="radio" :id="'sc-' + option" :value="option" name="billing-cycle"><label :for="'sc-' + option"><span class="sc-choice-top"><span class="sc-radio-dot"></span><strong>{{ t(option) }}</strong><span v-if="option === 'yearly' && yearlySaving > 0" class="sc-savings">{{ t('save', { percent: yearlySaving }) }}</span></span><span class="sc-choice-price">{{ formatPrice(packageData[option].price) }} <small>{{ currency }}</small></span><span class="sc-choice-help">{{ t(option + '_help') }}</span></label></div></fieldset>
          <p v-if="billingCycle === 'yearly'" class="sc-billing-note"><i class="bi bi-info-circle" aria-hidden="true"></i>{{ t('month_equivalent', { amount: formatPrice(currentPrice / 12), currency }) }}</p>
        </section>

        <section v-if="total > 0" class="sc-panel sc-section"><div class="sc-section-header"><span class="sc-step">02</span><div><h2 id="sc-payment-heading">{{ t('payment_title') }}</h2><p>{{ t('payment_help') }}</p></div></div>
          <fieldset class="sc-choice-grid sc-payments" aria-labelledby="sc-payment-heading">
            <div class="sc-choice"><input v-model="paymentMethod" :disabled="processing" type="radio" id="sc-card" value="card" name="payment-method"><label for="sc-card"><span class="sc-payment-top"><i class="bi bi-credit-card" aria-hidden="true"></i><span class="sc-radio-dot"></span></span><strong>{{ t('card') }}</strong><span class="sc-choice-help">{{ t('card_help') }}</span><img class="sc-payment-logo" :src="cardImage" alt="Visa & Mastercard"></label></div>
            <div class="sc-choice"><input v-model="paymentMethod" :disabled="processing" type="radio" id="sc-tamara" value="tamara" name="payment-method"><label for="sc-tamara"><span class="sc-payment-top"><i class="bi bi-wallet2" aria-hidden="true"></i><span class="sc-radio-dot"></span></span><strong>{{ t('tamara') }}</strong><span class="sc-choice-help">{{ t('tamara_help') }}</span><img class="sc-payment-logo sc-tamara-logo" :src="tamaraImage" :alt="t('tamara')"></label></div>
          </fieldset>
        </section>
        <div v-else class="sc-free-note" role="status"><i class="bi bi-check-circle" aria-hidden="true"></i><p>{{ t('free_help') }}</p></div>

        <section v-if="packageFeatures.length" class="sc-panel sc-section sc-benefits"><div class="sc-section-header"><span class="sc-feature-icon"><i class="bi bi-stars" aria-hidden="true"></i></span><div><h2>{{ t('features_title') }}</h2><p>{{ t('features_help') }}</p></div></div><ul><li v-for="(feature,index) in visibleFeatures" :key="index"><span><i class="bi bi-check2" aria-hidden="true"></i></span>{{ feature }}</li></ul><button v-if="packageFeatures.length > 6" type="button" class="sc-more" :aria-expanded="String(showAllFeatures)" @click="showAllFeatures = !showAllFeatures">{{ t(showAllFeatures ? 'less' : 'more') }}<i :class="showAllFeatures ? 'bi bi-chevron-up' : 'bi bi-chevron-down'" aria-hidden="true"></i></button></section>
      </div>

      <aside class="sc-summary sc-panel" aria-labelledby="sc-summary-heading"><div class="sc-summary-heading"><span class="sc-summary-icon"><i class="bi bi-receipt" aria-hidden="true"></i></span><h2 id="sc-summary-heading">{{ t('summary') }}</h2><p>{{ t('summary_help') }}</p></div>
        <div class="sc-summary-body"><div class="sc-summary-plan"><strong>{{ packageData.name }}</strong><span>{{ t(billingCycle) }}</span></div>
          <div class="sc-summary-row"><span>{{ t('subscription') }}</span><strong>{{ formatPrice(currentPrice) }} {{ currency }}</strong></div>
          <div v-if="discount > 0" class="sc-summary-row sc-discount-row"><span>{{ couponMessages.discount }}</span><strong>−{{ formatPrice(discount) }} {{ currency }}</strong></div>

          <div class="sc-coupon"><label for="sc-coupon-input"><i class="bi bi-ticket-perforated" aria-hidden="true"></i>{{ couponMessages.title }}</label><div class="sc-coupon-input"><input id="sc-coupon-input" v-model="couponInput" type="text" :placeholder="couponMessages.placeholder" maxlength="50" autocomplete="off" dir="ltr" :disabled="couponLoading || processing" @keydown.enter.prevent="applyCoupon"><button type="button" @click="applyCoupon" :disabled="couponLoading || processing">{{ couponLoading ? couponMessages.checking : couponMessages.apply }}</button></div><p v-if="couponMessage" class="sc-coupon-message" :class="couponValid ? 'is-valid' : 'is-invalid'" role="status" aria-live="polite">{{ couponMessage }}</p><div v-if="couponValid" class="sc-applied-code"><span><i class="bi bi-check-circle" aria-hidden="true"></i>{{ couponCode }}</span><button type="button" @click="removeCoupon()" :disabled="processing">{{ couponMessages.remove }}</button></div><p v-if="couponValid" class="sc-coupon-note">{{ couponMessages.one_time }}</p></div>

          <div class="sc-tax-breakdown"><div class="sc-summary-row"><span>{{ t('net_amount') }}</span><strong>{{ formatPrice(taxBreakdown.net) }} {{ currency }}</strong></div><div class="sc-summary-row"><span>{{ t('vat', { rate: vatRate }) }}</span><strong>{{ formatPrice(taxBreakdown.vat) }} {{ currency }}</strong></div></div>
          <div class="sc-total"><span>{{ t('total') }}</span><strong>{{ formatPrice(total) }} <small>{{ currency }}</small></strong><p>{{ t(billingCycle === 'yearly' ? 'billed_yearly' : 'billed_monthly') }}</p></div>
          <div class="sc-inclusive-note"><i class="bi bi-check-circle" aria-hidden="true"></i>{{ t('inclusive_note') }}</div>
          <p v-if="errorMessage" class="sc-error" role="alert">{{ errorMessage }}</p>
          <button type="submit" class="sc-button sc-button-primary sc-pay-button" :disabled="processing || couponLoading"><span v-if="processing" class="sc-spinner"></span><i v-else class="bi bi-lock" aria-hidden="true"></i>{{ processing ? t('processing') : (total === 0 ? couponMessages.no_payment : t('pay')) }}<i v-if="!processing" class="bi bi-arrow-right sc-pay-arrow" aria-hidden="true"></i></button>
          <p class="sc-trust"><i class="bi bi-shield-check" aria-hidden="true"></i>{{ total === 0 ? t('free_help') : t('trust') }}</p>
        </div>
      </aside>
    </form>
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
    plansUrl:{type:String,default:'/subscription/plans'},
    checkoutKey:{type:String,required:true},
    couponMessages:{type:Object,required:true},
    messages:{type:Object,required:true},
    vatRate:{type:Number,default:15},
    backUrl:{type:String,required:true},
    locale:{type:String,default:'en'},
    csrfToken:{type:String,required:true},
    cardImage:{type:String,default:''},
    tabbyImage:{type:String,default:''},
    tamaraImage:{type:String,default:''}
  },
  data(){
    return {
      attemptKey:this.checkoutKey,
      couponRequest:0,
      loading:true,
      showAllFeatures:false,
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
      return Math.max(0, Math.round(this.currentPrice * 100) - Math.round(Number(this.discount || 0) * 100)) / 100
    },
    taxBreakdown(){
      const cents = Math.round(this.total * 100)
      const rate = Math.round(this.vatRate * 100)
      const denominator = 10000 + rate
      const vatCents = Math.floor((cents * rate + Math.floor(denominator / 2)) / denominator)
      return { vat: vatCents / 100, net: (cents - vatCents) / 100 }
    },
    yearlySaving(){
      const annual = Number(this.packageData.monthly?.price || 0) * 12
      return annual > 0 ? Math.max(0, Math.round((1 - Number(this.packageData.yearly?.price || 0) / annual) * 100)) : 0
    },
    visibleFeatures(){ return this.showAllFeatures ? this.packageFeatures : this.packageFeatures.slice(0, 6) },
    packageFeatures(){
      if (this.packageData.display_features && this.packageData.display_features.length) return this.packageData.display_features
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
    billingCycle(){ this.removeCoupon() },
    paymentMethod(){ this.removeCoupon() },
    couponInput(value){
      if (this.couponCode && value.trim().toUpperCase() !== this.couponCode) this.removeCoupon(false)
      else if (this.couponLoading) { this.couponRequest++; this.couponLoading = false }
    }
  },
  mounted(){
    this.loadPackage()
  },
  methods: {
    t(key, params = {}) {
      return String(this.messages[key] || key).replace(/:([a-z_]+)/g, (match, name) => params[name] ?? match)
    },
    loadPackage() {
      this.loading = true
      this.errorMessage = ''

      axios.get(this.plansUrl)
          .then(response => {
            const packages = response.data.data.packages || []

            this.packageData = packages.find(item => String(item.id) === String(this.bundleId)) || {}

            if (!this.packageData.id) {
              this.errorMessage = this.t('invalid_plan')
              this.showPaymentError(this.errorMessage)
            }
          })
          .catch(error => {
            console.error('Unable to load subscription plan', error)
            this.errorMessage = error.response && error.response.data && error.response.data.message
                ? error.response.data.message
                : this.t('load_error')
            this.showPaymentError(this.errorMessage)
          })
          .finally(() => {
            this.loading = false
          })
    },

    applyCoupon() {
      const coupon = this.couponInput.trim()

      if (!coupon) {
        this.couponMessage = this.couponMessages.empty
        this.couponValid = false
        return
      }

      const requestId = ++this.couponRequest
      this.couponLoading = true
      this.couponMessage = ''
      this.errorMessage = ''

      axios.post(this.couponUrl, {
        coupon_code: coupon,
        bundle_id: this.bundleId,
        plan_id: this.selectedPlanId,
        cycle: this.billingCycle,
        payment_method: this.paymentMethod
      }, {
        headers: {'X-CSRF-TOKEN': this.csrfToken}
      })
          .then(response => {
            if (requestId !== this.couponRequest) return
            const data = response.data

            if (data.success) {
              this.discount = Number(data.discount || 0)
              this.couponCode = data.coupon_code
              this.selectedPlan.price = Number(data.subtotal)
              this.couponValid = true
              this.couponMessage = data.message || this.couponMessages.applied
            } else {
              this.discount = 0
              this.couponCode = ''
              this.couponValid = false
              this.couponMessage = data.message || this.couponMessages.invalid
            }
          })
          .catch(error => {
            if (requestId !== this.couponRequest) return
            this.discount = 0
            this.couponCode = ''
            this.couponValid = false
            this.couponMessage = error.response && error.response.data && error.response.data.message
                ? error.response.data.message
                : this.couponMessages.invalid
          })
          .finally(() => {
            if (requestId === this.couponRequest) this.couponLoading = false
          })
    },

    removeCoupon(clearInput = true) {
      this.couponRequest++
      this.couponLoading = false
      this.discount = 0
      this.couponCode = ''
      this.couponValid = false
      this.couponMessage = ''
      this.errorMessage = ''
      if (clearInput) this.couponInput = ''
    },

    submitForm() {
      if (this.processing || this.couponLoading) return
      if (this.couponInput.trim() && !this.couponValid) {
        this.couponMessage = this.couponMessages.apply_first
        return
      }

      if (!this.bundleId) {
        this.errorMessage = this.t('invalid_plan')
        this.showPaymentError(this.errorMessage)
        return
      }

      if (!this.selectedPlanId) {
        this.errorMessage = this.t('invalid_plan')
        this.showPaymentError(this.errorMessage)
        return
      }

      if (!this.paymentMethod) {
        this.errorMessage = this.t('payment_help')
        this.showPaymentError(this.errorMessage)
        return
      }

      this.processing = true
      this.errorMessage = ''

      axios.post(this.checkoutUrl, {
        bundle_id: this.bundleId,
        plan_id: this.selectedPlanId,
        cycle: this.billingCycle,
        payment_method: this.paymentMethod,
        coupon_code: this.couponCode || null,
        checkout_key: this.attemptKey,
        expected_total: this.total
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

            this.errorMessage = data.message || this.t('submit_error')
            if (data.retryable) this.attemptKey = this.newCheckoutKey()
            this.processing = false
            this.showPaymentError(this.errorMessage)
          })
          .catch(error => {
            console.error('Subscription checkout error', error)

            this.errorMessage = error.response && error.response.data && error.response.data.message
                ? error.response.data.message
                : this.t('submit_error')

            if (error.response?.data?.retryable) this.attemptKey = this.newCheckoutKey()

            this.processing = false
            this.showPaymentError(this.errorMessage)
          })
    },

    newCheckoutKey() {
      const bytes = crypto.getRandomValues(new Uint8Array(16))
      bytes[6] = (bytes[6] & 15) | 64
      bytes[8] = (bytes[8] & 63) | 128
      const hex = Array.from(bytes, byte => byte.toString(16).padStart(2, '0')).join('')
      return `${hex.slice(0,8)}-${hex.slice(8,12)}-${hex.slice(12,16)}-${hex.slice(16,20)}-${hex.slice(20)}`
    },

    showPaymentError(message) {
      if (window.Swal) {
        window.Swal.fire({
          icon: 'error',
          title: this.t('payment_error'),
          text: message,
          confirmButtonText: this.t('okay')
        })
      }
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
      return Number(value || 0).toLocaleString(this.locale, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
      })
    },

    goBack() {
      window.location.href = this.backUrl
    }
  }
}
</script>


<style scoped>
.sc-page{--sc-brand:#6d4aff;--sc-soft:#f3efff;--sc-ink:#25253e;--sc-muted:#9490a5;--sc-line:#eeebf4;max-width:1280px;margin:0 auto;padding:5px 0 30px;color:var(--sc-ink);font-family:inherit}.sc-page *{box-sizing:border-box}.sc-page :is(h1,h2,h3,p,fieldset){margin:0}.sc-page a{text-decoration:none}.sc-page :is(button,input){font:inherit}.sc-page :is(button,input,a):focus-visible{outline:3px solid #bcaaff;outline-offset:3px}.sc-back{display:inline-flex;align-items:center;gap:9px;color:#9b92ac;font-size:11px;font-weight:550;margin-bottom:24px}.sc-back:hover{color:var(--sc-brand)}.sc-header{display:flex;align-items:center;justify-content:space-between;gap:22px;margin-bottom:27px}.sc-eyebrow{font-size:10px;text-transform:uppercase;letter-spacing:1.8px;font-weight:700;color:var(--sc-brand);display:block;margin-bottom:8px}.sc-header h1{font-size:30px;font-weight:750;letter-spacing:-1px;line-height:1.25;color:var(--sc-ink)}.sc-header p{font-size:12px;line-height:1.7;color:var(--sc-muted);margin-top:9px}.sc-secure-badge{display:inline-flex;align-items:center;gap:7px;border:1px solid #e7e2f2;background:#fff;padding:8px 12px;border-radius:8px;font-size:10px;font-weight:550;color:#8b809f;white-space:nowrap}.sc-secure-badge i{color:#8f77d3;font-size:15px}.sc-layout{display:grid;grid-template-columns:minmax(0,1fr) 365px;gap:24px;align-items:start}.sc-main{display:flex;flex-direction:column;gap:20px;min-width:0}.sc-panel{background:#fff;border:1px solid var(--sc-line);border-radius:17px;box-shadow:0 2px 4px #28243e03}.sc-plan{display:flex;align-items:center;gap:16px;padding:23px}.sc-plan-mark{display:grid;place-items:center;width:54px;height:54px;flex-shrink:0;border-radius:16px;background:linear-gradient(145deg,#f1eaff,#f7f4ff);border:1px solid #e9dfff;color:#8663e6;font-size:27px}.sc-plan-description{flex:1;min-width:0}.sc-small-label{display:block;color:var(--sc-muted);font-size:9px;margin-bottom:5px}.sc-plan-description h2{font-size:20px;line-height:1.3;font-weight:700}.sc-plan-description p{font-size:10px;line-height:1.65;color:var(--sc-muted);margin-top:5px;max-width:310px}.sc-plan-price{display:flex;flex-direction:column;align-items:flex-end;gap:4px;flex-shrink:0}.sc-plan-price strong{font-size:22px;font-weight:700;letter-spacing:-.5px;white-space:nowrap}.sc-plan-price strong small{font-size:10px;letter-spacing:0;color:#8b7da5}.sc-plan-price>span{font-size:10px;color:var(--sc-muted)}.sc-plan-price>small{font-size:8px;color:#a69bb7;background:#f8f6fc;border-radius:4px;padding:3px 5px}.sc-section{padding:24px}.sc-section-header{display:flex;align-items:center;gap:12px;margin-bottom:22px}.sc-section-header h2{font-size:14px;font-weight:650;color:var(--sc-ink)}.sc-section-header p{font-size:11px;line-height:1.6;color:var(--sc-muted);margin-top:5px}.sc-step{display:grid;place-items:center;width:32px;height:32px;flex-shrink:0;border-radius:10px;border:1px solid #ece7f7;background:#faf8ff;color:#a18cc6;font-size:10px;font-weight:700}.sc-choice-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:13px;border:0;padding:0;min-width:0}.sc-choice{position:relative;min-width:0}.sc-choice>input{position:absolute;width:1px;height:1px;opacity:0;inset-inline-start:15px;top:15px}.sc-choice label{display:flex;flex-direction:column;min-height:145px;border:1px solid #eae6f2;border-radius:12px;padding:17px;cursor:pointer;background:#fff;transition:border-color .15s,background .15s,box-shadow .15s}.sc-choice>input:checked+label{border-color:#a78bee;background:#faf7ff;box-shadow:0 0 0 1px #a78bee20}.sc-choice>input:focus-visible+label{outline:3px solid #c0adff;outline-offset:3px}.sc-choice>input:disabled+label{cursor:wait;opacity:.65}.sc-choice-top{display:flex;align-items:center;gap:8px;min-height:24px}.sc-choice-top strong{font-size:12px;font-weight:650}.sc-radio-dot{display:inline-block;height:15px;width:15px;border:1.5px solid #d8d0e5;border-radius:50%;background:#fff;flex-shrink:0}.sc-choice>input:checked+label .sc-radio-dot{border:4px solid var(--sc-brand)}.sc-savings{margin-inline-start:auto;font-size:8px;font-weight:650;padding:4px 6px;border-radius:5px;background:#eee7ff;color:#9373ce;white-space:nowrap}.sc-choice-price{font-size:24px;letter-spacing:-.8px;font-weight:700;margin-top:16px;line-height:1.2}.sc-choice-price small{font-size:10px;letter-spacing:0;color:#9c8bb3;font-weight:550}.sc-choice-help{font-size:10px;color:var(--sc-muted);margin-top:7px;line-height:1.5}.sc-billing-note{display:flex;gap:7px;align-items:center;font-size:10px;color:#9b8bb0!important;margin-top:14px!important;line-height:1.6}.sc-payment-top{display:flex;align-items:center;justify-content:space-between;margin-bottom:15px}.sc-payment-top>i{font-size:22px;color:#b09bcf}.sc-payments label{min-height:155px;align-items:flex-start}.sc-payments .sc-payment-top{width:100%}.sc-payments strong{font-size:11px;font-weight:650}.sc-payment-logo{height:18px;max-width:110px;object-fit:contain;object-position:left center;margin-top:17px}.sc-tamara-logo{height:21px;max-width:86px}.sc-feature-icon{display:grid;place-items:center;width:34px;height:34px;flex-shrink:0;border-radius:10px;background:#f6f3fc;color:#a38bc5;font-size:18px}.sc-benefits ul{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:15px 20px;list-style:none;margin:0;padding:0}.sc-benefits li{display:flex;align-items:flex-start;gap:9px;font-size:10px;line-height:1.7;color:#92879f}.sc-benefits li>span{display:grid;place-items:center;width:17px;height:17px;flex-shrink:0;border-radius:5px;background:#f4effc;color:#9d85bd;font-size:12px;margin-top:1px}.sc-more{display:inline-flex;align-items:center;gap:8px;border:0;border-top:1px solid var(--sc-line);background:none;color:#9675c5;padding:15px 0 0;margin-top:20px;font-size:10px;font-weight:600;cursor:pointer}.sc-summary{position:sticky;top:93px;overflow:hidden}.sc-summary-heading{position:relative;background:linear-gradient(130deg,#f4efff,#fcfaff);padding:25px;border-bottom:1px solid #ece5f8}.sc-summary-icon{width:34px;height:34px;display:grid;place-items:center;background:#ede4fc;border:1px solid #e9dff8;border-radius:11px;color:#ae8bd6;font-size:18px;margin-bottom:14px}.sc-summary-heading h2{font-size:17px;font-weight:650;letter-spacing:-.3px}.sc-summary-heading p{font-size:10px;color:#a394b7;line-height:1.7;margin-top:6px}.sc-summary-body{padding:24px}.sc-summary-plan{display:flex;justify-content:space-between;align-items:center;gap:15px;margin-bottom:24px}.sc-summary-plan strong{font-size:14px;font-weight:650}.sc-summary-plan>span{padding:5px 9px;background:#f6f3fc;border:1px solid #ede7f6;border-radius:6px;font-size:9px;color:#a18eBA}.sc-summary-row{display:flex;align-items:baseline;justify-content:space-between;gap:12px;font-size:11px;line-height:1.6;margin-bottom:13px}.sc-summary-row>span{color:#9c90ad}.sc-summary-row>strong{font-size:11px;font-weight:550;white-space:nowrap;color:#81708f}.sc-discount-row>span,.sc-discount-row>strong{color:#62a489}.sc-coupon{border:1px dashed #e6dff0;border-radius:11px;padding:14px;margin-top:21px;margin-bottom:21px;background:#fdfcfe}.sc-coupon>label{display:flex;align-items:center;gap:7px;font-size:10px;font-weight:550;color:#9b87b0;margin-bottom:11px}.sc-coupon>label>i{font-size:14px}.sc-coupon-input{display:flex;gap:7px}.sc-coupon-input input{width:100%;min-width:0;border:1px solid #e8e0ef;border-radius:7px;background:#fff;padding:9px;font-size:10px;color:#8e799e;min-height:36px}.sc-coupon-input input::placeholder{color:#c0b1ca}.sc-coupon-input>button{border:1px solid #e7dbf2;border-radius:7px;padding:7px 12px;background:#f0e8fa;color:#9b77b8;font-size:10px;font-weight:600;cursor:pointer}.sc-coupon-message{font-size:10px;line-height:1.6;margin-top:9px!important}.sc-coupon-message.is-valid{color:#5b9d7c}.sc-coupon-message.is-invalid{color:#bd7186}.sc-applied-code{display:flex;align-items:center;justify-content:space-between;gap:9px;font-size:10px;color:#9873b8;margin-top:10px}.sc-applied-code>span{display:flex;align-items:center;gap:5px;overflow-wrap:anywhere}.sc-applied-code button{background:none;border:0;color:#a58ab5;font-size:9px;text-decoration:underline;cursor:pointer}.sc-coupon-note{font-size:9px;color:#b19bbd;line-height:1.6;margin-top:9px!important}.sc-tax-breakdown{border-bottom:1px solid var(--sc-line);padding-bottom:6px}.sc-total{padding-top:22px;display:flex;flex-direction:column;align-items:flex-start;gap:9px}.sc-total>span{font-size:11px;color:#a18eaf;font-weight:550}.sc-total>strong{font-size:35px;letter-spacing:-1.5px;line-height:1.2;font-weight:750;color:#56416b}.sc-total>strong>small{font-size:12px;letter-spacing:0;font-weight:550;color:#b59bc5}.sc-total>p{font-size:9px;color:#b09bbe}.sc-inclusive-note{display:flex;align-items:center;gap:6px;margin-top:13px;color:#a99bb6;font-size:9px;line-height:1.6}.sc-inclusive-note>i{color:#a6bead}.sc-button{display:inline-flex;align-items:center;justify-content:center;gap:8px;border:1px solid transparent;border-radius:9px;padding:12px 16px;font:inherit;font-size:11px;font-weight:600;cursor:pointer;transition:background .15s,box-shadow .15s}.sc-button-primary{background:var(--sc-brand);color:#fff;box-shadow:0 4px 9px #6d4aff1c}.sc-button-primary:hover{background:#5b3cde;box-shadow:0 5px 12px #6d4aff25}.sc-button:disabled,.sc-coupon-input button:disabled{opacity:.6;cursor:wait}.sc-pay-button{width:100%;margin-top:23px;min-height:43px}.sc-pay-arrow{margin-inline-start:auto}.sc-trust{display:flex;align-items:flex-start;justify-content:center;gap:5px;font-size:9px;color:#b2a3bc;margin-top:13px!important;line-height:1.6;text-align:center}.sc-trust>i{margin-top:1px}.sc-error{margin-top:16px!important;padding:11px;border-radius:8px;background:#fff1f3;color:#b96079;font-size:11px;line-height:1.6}.sc-state{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:20px;background:#fff;border:1px solid var(--sc-line);border-radius:16px;padding:65px 25px;color:#9b8fa8;font-size:13px}.sc-state h2{font-size:18px}.sc-spinner{display:inline-block;border:2px solid currentColor;border-right-color:transparent;border-radius:50%;width:17px;height:17px;animation:sc-spin .8s linear infinite}.sc-free-note{display:flex;gap:13px;padding:23px;border-radius:14px;background:#eef9f3;border:1px solid #d7ede1;color:#6c9a7c;font-size:12px;line-height:1.7}.sc-free-note>i{font-size:21px}@keyframes sc-spin{to{transform:rotate(360deg)}}
:global([dir="rtl"]) .sc-back>i,:global([dir="rtl"]) .sc-pay-arrow{transform:rotate(180deg)}:global([dir="rtl"]) .sc-header h1,:global([dir="rtl"]) .sc-eyebrow{letter-spacing:0}
@media(max-width:1199px){.sc-layout{grid-template-columns:minmax(0,1fr) 325px;gap:18px}.sc-section,.sc-plan{padding:20px}.sc-plan{flex-wrap:wrap;gap:13px}.sc-plan-price{align-items:flex-start;width:100%;padding-top:14px;border-top:1px solid var(--sc-line);flex-direction:row;align-items:baseline;gap:7px}.sc-plan-price>small{margin-inline-start:auto}.sc-header h1{font-size:27px}.sc-choice label{padding:14px}.sc-choice-price{font-size:21px}.sc-summary-body,.sc-summary-heading{padding:21px}.sc-savings{font-size:7px;padding:4px}.sc-benefits ul{grid-template-columns:1fr;gap:13px}}
@media(max-width:991px){.sc-layout{grid-template-columns:minmax(0,1fr)}.sc-summary{position:static}.sc-plan{flex-wrap:nowrap}.sc-plan-price{width:auto;flex-direction:column;border:0;padding:0;gap:4px;align-items:flex-end}.sc-plan-price>small{margin:0}.sc-benefits ul{grid-template-columns:repeat(2,minmax(0,1fr))}.sc-choice label{padding:20px}.sc-summary-heading{padding:23px}.sc-summary-body{padding:25px}.sc-summary-heading .sc-summary-icon{float:inline-end;margin:0}.sc-total{flex-direction:row;flex-wrap:wrap;align-items:baseline;justify-content:space-between}.sc-total>strong{font-size:32px}.sc-total>p{width:100%;text-align:end}.sc-trust{font-size:10px}.sc-coupon-input>button{min-width:90px}.sc-coupon-input input{font-size:12px}}
@media(max-width:575px){.sc-back{margin-bottom:20px}.sc-header{gap:14px;align-items:flex-start;flex-direction:column;margin-bottom:20px}.sc-header h1{font-size:25px}.sc-header p{font-size:11px}.sc-secure-badge{padding:6px 9px;font-size:9px}.sc-layout{gap:17px}.sc-main{gap:16px}.sc-plan{flex-wrap:wrap;padding:18px}.sc-plan-mark{width:43px;height:43px;border-radius:12px;font-size:22px}.sc-plan-description h2{font-size:18px}.sc-plan-price{flex-direction:row;align-items:baseline;gap:7px;width:100%;padding-top:14px;border-top:1px solid var(--sc-line)}.sc-plan-price>small{margin-inline-start:auto}.sc-section{padding:19px 16px}.sc-section-header{gap:10px;margin-bottom:18px}.sc-section-header h2{font-size:12px}.sc-section-header p{font-size:10px}.sc-choice-grid{gap:9px}.sc-choice label{padding:13px 10px;min-height:138px}.sc-choice-top{flex-wrap:wrap;gap:6px}.sc-choice-top strong{font-size:11px}.sc-savings{font-size:7px;margin-inline-start:0}.sc-choice-price{font-size:20px;margin-top:15px}.sc-choice-price small{font-size:8px}.sc-choice-help{font-size:9px}.sc-payment-top{margin-bottom:13px}.sc-payments strong{font-size:10px}.sc-payments label{min-height:150px}.sc-benefits ul{grid-template-columns:1fr}.sc-summary-heading,.sc-summary-body{padding:21px}.sc-summary-heading p{font-size:10px;max-width:230px}.sc-total>strong{font-size:30px}.sc-summary-row{font-size:10px}.sc-summary-row>strong{font-size:10px}.sc-coupon-input input{font-size:11px}.sc-coupon-input>button{min-width:65px}.sc-inclusive-note{font-size:9px}.sc-free-note{padding:18px;font-size:11px}}
@media(prefers-reduced-motion:reduce){.sc-page *{transition:none!important;animation:none!important}}
</style>

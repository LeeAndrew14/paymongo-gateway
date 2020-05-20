# Paymongo Payment Gateway for Woocommerce
(experimental WIP)

Integration of Paymongo payment gateway for Woocommerce


**Installation:**

Download `paymongo-gateway` as zip then

Go to wordpress admin page then navigate:

  - Plugins -> Add New -> Upload Plugin -> Browse the `paymongo-gateway.zip` file

then press **Install Now**

Look for **PayMongo Payment Gateway** on the list of plugin below then press **Activate** if its deactivated


**Setup:**

Sign up [here](https://dashboard.paymongo.com/signup) to get PayMongo API Keys or login [here](https://dashboard.paymongo.com/developers) if you already have an PayMongo account.

Then navigate to the developers page to get the PayMongo API Keys

Go to Wordpress admin page then navigate:

  - WooCommerce -> Settings -> Payments 

Enable **E-wallet** or **Credit/Debit Card** or both then press **Setup**

*Note: You need to setup both **E-wallet** and **Credit/Debit Card** if you will support both*

Enable **Test mode** for development, uncheck for production

Use **Secret Key(Live)** for production and **Secret Key(Test)** for development mode

Paste each key in its designated fields, more info about [PayMongo API Keys](https://developers.paymongo.com/docs/authentication)

Ignore **Test/Live Publishable Key** fields for now, it can be used to generate token
but for now Paymongo does not require tokenized payload


Then your're all good!

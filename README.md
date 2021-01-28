# App system example
  
This app allows you to generate and print order lists for each order.
These lists contain all products of that order in a print friendly way.
So that they can be used as a checklist during packing.

## App actions

- If a customer orders something in the checkout, an order list will be saved in the custom fields of an order.
- This can also be done with the action button in the admin order details.
- You can see the list of all orders with a print button if you navigate to `Extensions > My extensions` and click the `open app` link for the Swag Example App.

## Caution

This is a pre configured app.  
You should **not** use this in production.  
In order to use this in production, you need to change the `APP_NAME` and the `APP_SECRET`.

The  `APP_SECRET` is needed to process the registration.  
Due to this everyone could register their shops to your app if you would use the default `APP_SECRET`.  

The `APP_NAME` is an unique identifier for your app.  
To use multiple apps simultaneously for testing purposes, you also need to change the `APP_NAME`. 

The `APP_NAME` and the `APP_SECRET` are both located in the [.platform.app.yaml](.platform.app.yaml) file.  
They also need to be changed in your `manifest.xml` file.

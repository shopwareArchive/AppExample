# App system example
  
This app allows you to generate and print order lists for each order.
These lists contain all products of that order in a print friendly way.
So that they can be used as a checklist during packing.

## Caution

This is a pre configured app.  
You should **not** use this in production.  
In order to use this in production, you need to change the `APP_NAME` and the `APP_SECRET`.

The  `APP_SECRET` is needed to process the registration.  
Due to this everyone could register their shops to your app if you would use the default `APP_SECRET`.  

The `APP_NAME` is an unique identifier for your app.  
To use multiple apps simultaneously for testing purposes, you also need to change the `APP_NAME`. 

The `APP_NAME` and the `APP_SECRET` are both located in the [.platform.app.yaml](.platform.app.yaml) file.  
They also need to be changed in your `manifest.xml` file

## The manifest.xml

```xml
<?xml version="1.0" encoding="UTF-8"?>
<manifest xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:noNamespaceSchemaLocation="https://raw.githubusercontent.com/shopware/app-system/0.1.0/src/Core/Content/App/Manifest/Schema/manifest-1.0.xsd">
    <meta>
        <name>SwagExampleApp</name>
        <label>Swag Example App</label>
        <description>Example App</description>
        <description lang="de-DE">Beispiel App</description>
        <author>shopware AG</author>
        <copyright>(c) by shopware AG</copyright>
        <version>1.0.0</version>
    </meta>
    <setup>
        <registrationUrl>https://your-app.url.com/registration</registrationUrl>
        <secret>143af21f36dda6b4bc40df8cb045616d</secret>
    </setup>

    <admin>
        <action-button action="addOrderList" entity="order" view="detail" url="https://your-app-url.com/actionbutton/add/orderlist">
            <label>Add order list</label>
            <label lang="de-DE">Bestellliste hinzufügen</label>
        </action-button>

        <module name="orderList" source="https://your-app.url.com/iframe/orderlist">
            <label>Order list</label>
            <label lang="de-DE">Bestellliste</label>
        </module>
    </admin>

    <permissions>
        <create>state_machine_history</create>
        <read>order</read>
        <update>order</update>
    </permissions>

    <custom-fields>
        <custom-field-set>
            <name>swag_orderlist</name>
            <label>Order list</label>
            <related-entities>
                <order/>
            </related-entities>
            <fields>
                <text name="order-list-link">
                    <position>1</position>
                    <label>Order list link</label>
                    <label lang="de-DE">Bestellliste Link</label>
                </text>
                <text-area name="order-list">
                    <position>2</position>
                    <label>Order list</label>
                    <label lang="de-DE">Bestellliste</label>
                </text-area>
            </fields>
        </custom-field-set>
    </custom-fields>

    <webhooks>
        <webhook name="checkoutOrderPlaced" url="https://your-app.url.com/hooks/order/placed" event="checkout.order.placed"/>
        <webhook name="appLifecycleDeleted" url="https://your-app.url.com/applifecycle/deleted" event="app_deleted"/>
    </webhooks>
</manifest>
```
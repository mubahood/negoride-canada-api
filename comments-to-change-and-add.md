# Negoride Canada - Changes & Features

**From:** Mr Hambren Canada  
**Date:** December 11-12, 2025

---

## ✅ COMPLETED

1. App name: Negoride canada.
**→ Rebrand entire app to "Negoride Canada"** ✅

---

## 📋 PENDING

2. Special car.
**→ Add "Special Car" as a vehicle category**

3. Taxi and rideshare
**→ Keep taxi and rideshare service types**

4. Change bodaboda to Courier [ eg. for parcels, goods, and other deliveries.
**→ Rename "Bodaboda" to "Courier" for parcel/goods delivery**

5. Change ambulance to MOVERS. Movers bebasegula abantu.
**→ Rename "Ambulance" to "Movers" for moving services**

6. Add airport pickup.
**→ Add airport pickup as a service option**

7. Canadian currency.
**→ Change currency to CAD (Canadian Dollar)**

8. In app Payments + Escrow + Automatic payouts( using strip connect and pay pal market place.
**→ Integrate Stripe Connect and PayPal for in-app payments with escrow and automatic payouts**

9. Integrate instant on stripe. Where we can pay drivers once the trip is completed.
**→ Use Stripe Instant Payouts to pay drivers immediately after trip completion**

10. 10 fully working and active notifications.
**→ Implement 10 working notification types (ride requests, driver arrival, payment, etc.)**

11. Strict visible terms and conditions, safely rules, and app policy.
**→ Make terms, conditions, safety rules, and policies clearly visible in the app**

12. Audio recording.
**→ Add in-trip audio recording feature**

13. Abusive protection and disputes button
**→ Add button for reporting abuse and filing disputes**

14. Commission as to be cut automatically by strip. No manual work.
**→ Configure Stripe Connect to automatically deduct platform commission**

15. Drivers wallet to view their earnings, payouts status.
**→ Create driver wallet screen showing earnings and payout status**

16. Customer rating to drivers. If the driver mishaves many times through bad rating. Will lose the account.
**→ Implement automatic account suspension for drivers with consistently bad ratings**

17. My full active dashboard to tract all activities
**→ Build admin dashboard to track all app activities** 


<!-- hr -->
## Features
# Negoride Canada - Requirements List

[App Configuration]
app_name = Negoride Canada
currency = CAD

we are lierarlly we are going to have two types of rides
1. Rideshare
    - Rideshare will be stand alone module where drivers will have ability schedule trips/joneys that they are going to take, propose available saets and prices. Customers ordering for ride share will be able to browse a list of the scheduled trips, select a trip that fits their needs and book a seat. this is already implemented in the app. we shall just need to master it later.
2. Car Hire
    - Car Hire, drivers will have a bility to go online and wait for customers to request rides. Customers will be able to request rides and get picked from their locations. this is the main module that we have been working on.
    
    we shall mainatin ride share and its respective logic as it is. we shall just need to master it later. ✅
    - Under Car Hire we are basically going to have the following service types. ✅
        - Rplace boda boda with -> Special Car Hire ✅
        - replace ambulace with "Airport Pickup" ✅
        - add Movers ✅
        - and Courier & Deliveries ✅
        only the above 4 service types will be under Car Hire module. ✅

    - The logic of Car Hire is also already implemented in the app. we shall just need to master it.
Please analyze the above two modules and give us feedback if you have any questions.
Analyze the current code base and understand it very well of how it works and how to make the changes.

let us start with the home page.
remove "Easily find the cheapest ride with bargaining power in your hand." from the home page. ✅
remove the buttons below the button of "Order a Ride" on the home page. i.e,remove the button with tax, boda and special. ✅
relace it with autmaitc text slider that will be showing different service types that we are going to offer. as mentioned above we are going to have the following service types. ✅


[Service Types]
service_1 = Special Car
service_2 = Taxi and Rideshare
service_3 = Courier (parcels, goods, and deliveries)
service_4 = Movers (for moving people and belongings)
service_5 = Airport Pickup

[Payment Integration]
payment_methods = In-app Payments, Escrow, Automatic Payouts
payment_providers = Stripe Connect, PayPal Marketplace
instant_payout = Stripe Instant (pay drivers on trip completion)

[Features]
notifications = 10 fully working and active notifications
audio_recording = Enabled
terms_and_conditions = Strict and visible
safety_rules = Strict and visible
app_policy = Strict and visible

SO HERE IS THE FINAL LIST.
 1.⁠ ⁠App name: Negoride canada.
 2.⁠ ⁠Special car.
 3.⁠ ⁠Taxi and rideshare
 4.⁠ ⁠Change bodaboda to Courier [ eg. for parcels, goods, and other deliveries.
 5.⁠ ⁠Change ambulance to MOVERS. Movers bebasegula abantu. 
 6.⁠ ⁠Add airport pickup.
 7.⁠ ⁠Canadian currency.
 8.⁠ ⁠In app Payments + Escrow + Automatic payouts( using strip connect and pay pal market place. 
 9.⁠ ⁠Integrate instant on stripe. Where we can pay drivers once the trip is completed.
10.⁠ ⁠10 fully working and active notifications.
11.⁠ ⁠Strict visible terms and conditions, safely rules, and app policy.
12.⁠ ⁠Audio recording.
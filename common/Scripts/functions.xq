declare function local:order-value($po as element(purchase-order))
   as xs:double {
      sum($po/order-item/(@price * @quantity))
};
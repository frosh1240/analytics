<?php

class AnalyticsAjaxModuleFrontController extends ModuleFrontController
{
    private function search($id)
    {
        $sql = Db::getInstance()->executeS('SELECT * FROM `' . _DB_PREFIX_ . 'order_status_analytics` WHERE id_order = ' . (int)$id);

        return $sql;
    }

    private function updateDataTable($data)
    {
        $id = $data[0]['id'];
        $reference = $data[0]['reference'];
        $state = $data[0]['current_state'];

        $searchs = $this->search($id);

        if ($searchs) {

            $insert = Db::getInstance()->execute(
                'UPDATE `' . _DB_PREFIX_ . 'order_status_analytics` 
                SET state = ' . (int)$state . ',status_update = ' . 0 . '
                WHERE id_order = ' . (int)$id . '
                '
            );

            if ($insert) return json_encode('success');
        } else {
            return json_encode('not_exist');
        }
    }

    private function getOrderData($reference)
    {
        $sql = 'SELECT id_order as id, reference, current_state FROM `' . _DB_PREFIX_ . 'orders` WHERE reference = "' . pSQL($reference) . '"';
        $request = Db::getInstance()->executeS($sql);



        die(json_encode($this->updateDataTable($request)));
    }

    public function postProcess()
    {
        $productId = (int)Tools::getValue('product_id');


        $product = new Product($productId, true, $this->context->language->id);
        $category = new Category((int) $product->id_category_default, $this->context->language->id);

        $data = [
            'id_product' => $product->id,
            'reference'  => $product->reference,
            'name'       => $product->name,
            'price'      => Product::getPriceStatic($productId, true),
            'currency'   => $this->context->currency->iso_code,
            'affiliation' => Context::getContext()->shop->name,
            'itembrand'  => $product->manufacturer_name,
            'category'   => $category->name,
            'attributes' => '',
        ];
        $referenceCode = Tools::getValue('reference_code');
        if ($referenceCode) $this->getOrderData($referenceCode);

        die(json_encode($data));
    }
}

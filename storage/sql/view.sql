--收支报表视图
CREATE VIEW parking_daily_cash_flow AS
SELECT
    parking_id,
    DATE(time) AS date,
    SUM(pay_price) AS total_income,
    SUM(CASE WHEN order_type = 'parking' THEN pay_price ELSE 0 END) AS parking_income,
	  SUM(CASE WHEN order_type = 'parking_recovery' THEN pay_price ELSE 0 END) AS parking_recovery,
    SUM(CASE WHEN order_type = 'parking_monthly' THEN pay_price ELSE 0 END) AS parking_monthly_income,
    SUM(CASE WHEN order_type = 'parking_stored' THEN pay_price ELSE 0 END) AS parking_stored_income,
    SUM(CASE WHEN order_type = 'merch_recharge' THEN pay_price ELSE 0 END) AS merch_recharge_income,
	  ROUND(SUM(handling_fees),2) AS handling_fees,
    SUM(CASE WHEN order_type = 'refund' THEN -refund_price ELSE 0 END) AS total_refund,
    ROUND((SUM(pay_price) -ROUND(SUM(handling_fees),2)-SUM(CASE WHEN order_type = 'refund' THEN -refund_price ELSE 0 END)),2) AS net_income
FROM
(
    SELECT
    parking_id,
    order_type,
    pay_price,
		round(handling_fees/100,4) as handling_fees,
		0 as refund_price,
		pay_time AS time
    FROM yun_pay_union
    WHERE pay_status = 1 AND pay_type <> 'underline' AND pay_type<>'stored' AND id NOT IN (select pay_id FROM yun_parking_records_filter where pay_id is not null)
    UNION ALL
    SELECT
        parking_id,
        'refund' AS order_type,
        0 AS pay_price, -- 将退款转换为负值
				0 as handling_fees,
        refund_price*-1 as refund_price,
			  refund_time AS time
    FROM yun_pay_refund
) AS combined_data
GROUP BY parking_id, DATE(time)
ORDER BY parking_id, DATE(time) DESC;
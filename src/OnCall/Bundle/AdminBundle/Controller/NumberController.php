<?php

namespace OnCall\Bundle\AdminBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use OnCall\Bundle\AdminBundle\Model\MenuHandler;
use Symfony\Component\HttpFoundation\Response;
use OnCall\Bundle\AdminBundle\Entity\Number;
use OnCall\Bundle\AdminBundle\Model\NumberType;

class NumberController extends Controller
{
    public function indexAction()
    {
        $em = $this->getDoctrine()->getManager();
        $req = $this->getRequest();

        // get accounts (all users who have no roles (ROLE_USER)
        $dql = 'select u from OnCall\Bundle\AdminBundle\Entity\User u where u.roles = :role';
        $acc_query = $em->createQuery($dql)
            ->setParameter('role', 'a:0:{}');
        $accounts = $acc_query->getResult();

        // get numbers
        $repo = $this->getDoctrine()->getRepository('OnCallAdminBundle:Number');
        $num_query = $repo->createQueryBuilder('n');
        
        $type = $req->get('type');
        $usage = $req->get('usage');
        
        // get types
        $types = NumberType::getAll();

        // usage filter
        if ($usage === '1')
            $num_query->where('n.user is not null');
        else if ($usage === '0')
            $num_query->where('n.user is null');

        // type filter
        if ($type != null && $type !== '')
        {
            $num_query->andWhere('n.type = :type')
                ->setParameter('type', $type);
        }

        // sort by
        $num_query->orderBy('n.id', 'asc');

        // actual query
        $numbers = $num_query->getQuery()->getResult();

        // get role hash for menu
        $user = $this->getUser();
        $role_hash = $user->getRoleHash();

        // TODO: messages?
        return $this->render(
            'OnCallAdminBundle:Number:index.html.twig',
            array(
                'sidebar_menu' => MenuHandler::getMenu($role_hash, 'number'),
                'accounts' => $accounts,
                'numbers' => $numbers,
                'types' => $types,
                'type' => $type,
                'usage' => $usage
            )
        );
    }

    protected function updateNumber(Number $num, $data)
    {
        // TODO: cleanup parameters / default value
        $provider = trim($data['provider']);
        $type = $data['type'];
        $price_buy = $data['price_buy'];
        $price_resale = $data['price_resale'];

        $num->setProvider($provider)
            ->setType($type)
            ->setPriceBuy($price_buy)
            ->setPriceResale($price_resale);
    }

    public function createMultipleAction()
    {
        $data = $this->getRequest()->request->all();
        $em = $this->getDoctrine()->getManager();

        // get numbers
        $numbers = explode("\n", $data['numbers']);
        $nlen = count($numbers);

        // trim numbers
        // TODO: check if numbers already exist
        for ($i = 0; $i < $nlen; $i++)
            $numbers[$i] = trim($numbers[$i]);


        // create the numbers
        foreach ($numbers as $num_text)
        {
            $num = new Number($num_text);
            $this->updateNumber($num, $data);
            $em->persist($num);
        }
        $em->flush();

        return $this->redirect($this->generateUrl('oncall_admin_numbers'));
    }

    public function getAction($id)
    {
        $repo = $this->getDoctrine()->getRepository('OnCallAdminBundle:Number');
        $num = $repo->find($id);
        if ($num == null)
        {
            // TODO: error message?
            return $this->redirect($this->generateUrl('oncall_admin_numbers'));
        }

        return new Response($num->jsonify());
    }

    public function updateAction($id)
    {
        $data = $this->getRequest()->request->all();
        $em = $this->getDoctrine()->getManager();

        // find
        $repo = $this->getDoctrine()->getRepository('OnCallAdminBundle:Number');
        $num = $repo->find($id);
        if ($num == null)
        {
            // TODO: error message?
            return $this->redirect($this->generateUrl('oncall_admin_numbers'));
        }

        // update
        $this->updateNumber($num, $data);
        $em->flush();

        return $this->redirect($this->generateUrl('oncall_admin_numbers'));
    }

    public function assignAction()
    {
    }

    public function deleteAction($id)
    {
        $em = $this->getDoctrine()->getManager();

        // find
        $repo = $this->getDoctrine()->getRepository('OnCallAdminBundle:Number');
        $num = $repo->find($id);
        if ($num == null)
        {
            // TODO: error message?
            return $this->redirect($this->generateUrl('oncall_admin_numbers'));
        }

        // check if we can delete
        if ($num->isInUse())
        {
            // TODO: error message?
            return $this->redirect($this->generateUrl('oncall_admin_numbers'));
        }

        // delete
        $em->remove($num);
        $em->flush();

        return $this->redirect($this->generateUrl('oncall_admin_numbers'));
    }
}
